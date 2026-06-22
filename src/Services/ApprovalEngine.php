<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Services;

use BackedEnum;
use CoringaWc\FilamentActionApprovals\Enums\ActionType;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalOperation;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Enums\StepInstanceStatus;
use CoringaWc\FilamentActionApprovals\Events\ApprovalCancelled;
use CoringaWc\FilamentActionApprovals\Events\ApprovalCommented;
use CoringaWc\FilamentActionApprovals\Events\ApprovalCompleted;
use CoringaWc\FilamentActionApprovals\Events\ApprovalDelegated;
use CoringaWc\FilamentActionApprovals\Events\ApprovalRejected;
use CoringaWc\FilamentActionApprovals\Events\ApprovalStepCompleted;
use CoringaWc\FilamentActionApprovals\Events\ApprovalSubmitted;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Notifications\ApprovalApprovedNotification;
use CoringaWc\FilamentActionApprovals\Notifications\ApprovalCancelledNotification;
use CoringaWc\FilamentActionApprovals\Notifications\ApprovalRejectedNotification;
use CoringaWc\FilamentActionApprovals\Notifications\ApprovalRequestedNotification;
use CoringaWc\FilamentActionApprovals\Support\ApprovalActionKey;
use CoringaWc\FilamentActionApprovals\Support\ApprovalActionRegistry;
use CoringaWc\FilamentActionApprovals\Support\ApprovalModels;
use CoringaWc\FilamentActionApprovals\Support\CurrentPanelUser;
use CoringaWc\FilamentActionApprovals\Support\SensitiveDataRedactor;
use CoringaWc\FilamentActionApprovals\Support\UserModelKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class ApprovalEngine
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  callable(Approval): mixed|null  $afterApprovalCreated
     */
    public function submit(Model $approvable, ?ApprovalFlow $flow = null, Model|int|string|null $submittedBy = null, ?string $actionKey = null, array $metadata = [], string|BackedEnum|null $action = null, ?callable $afterApprovalCreated = null): Approval
    {
        $actionKey = $this->resolveActionKeyInput($approvable, $actionKey, $action);
        $flowModel = ApprovalModels::flow();
        $flow ??= $flowModel::findSubmissionFlowForModel($approvable, $actionKey);
        $submitter = $this->resolveSubmitter($submittedBy);

        if (! $flow) {
            throw (new ModelNotFoundException)->setModel($flowModel);
        }

        $resolvedActionKey = $this->resolveSubmittedActionKey($flow, $actionKey);

        if ($this->shouldBlockConcurrentPendingApproval($approvable, $resolvedActionKey)) {
            throw ValidationException::withMessages([
                'approval' => __('filament-action-approvals::approval.actions.pending_request_exists'),
            ]);
        }

        try {
            return DB::transaction(function () use ($afterApprovalCreated, $approvable, $flow, $metadata, $resolvedActionKey, $submitter) {
                $approvalMetadata = SensitiveDataRedactor::metadata(array_filter([
                    ...$metadata,
                    'action_key' => $resolvedActionKey,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''));

                $approvalModel = ApprovalModels::approval();
                $stepInstanceModel = ApprovalModels::stepInstance();

                $approval = $approvalModel::create([
                    'approval_flow_id' => $flow->getKey(),
                    'approvable_type' => $approvable->getMorphClass(),
                    'approvable_id' => $approvable->getKey(),
                    'status' => ApprovalStatus::Pending,
                    'action_key' => $resolvedActionKey,
                    'submitted_by' => $submitter['user_id'],
                    'submitted_by_type' => $submitter['actor_type'],
                    'submitted_by_id' => $submitter['actor_id'],
                    'submitted_at' => now(),
                    'metadata' => $approvalMetadata === [] ? null : $approvalMetadata,
                ]);

                foreach ($flow->steps as $step) {
                    $approverIds = $step->resolveApproverIds($approvable);

                    $stepInstanceModel::create([
                        'approval_id' => $approval->getKey(),
                        'approval_step_id' => $step->getKey(),
                        'order' => $step->order,
                        'type' => $step->type,
                        'status' => StepInstanceStatus::Pending,
                        'required_approvals' => $step->required_approvals,
                        'assigned_approver_ids' => $approverIds,
                    ]);
                }

                $approval->actions()->create([
                    'user_id' => $submitter['user_id'],
                    'type' => ActionType::Submitted,
                    'metadata' => array_filter([
                        'action_key' => $resolvedActionKey,
                        'actor_type' => $submitter['actor_type'],
                        'actor_id' => $submitter['actor_id'],
                    ], static fn (mixed $value): bool => $value !== null && $value !== ''),
                ]);

                $this->activateNextStep($approval);

                if ($afterApprovalCreated !== null) {
                    $afterApprovalCreated($approval->refresh());
                }

                $this->afterCommit(function () use ($approval, $approvable): void {
                    event(new ApprovalSubmitted($approval));
                    $this->fireModelCallback($approvable, 'onApprovalSubmitted', $approval);
                });

                return $approval;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'approval' => __('filament-action-approvals::approval.actions.pending_request_exists'),
            ]);
        }
    }

    protected function resolveSubmittedActionKey(ApprovalFlow $flow, ?string $actionKey): ?string
    {
        if (filled($actionKey)) {
            return $actionKey;
        }

        return is_string($flow->action_key) && filled($flow->action_key)
            ? $flow->action_key
            : null;
    }

    protected function resolveActionKeyInput(Model $approvable, ?string $actionKey, string|BackedEnum|null $action): ?string
    {
        $normalizedAction = ApprovalActionKey::normalize($approvable, $action);

        if ($normalizedAction === null) {
            return $actionKey;
        }

        if (filled($actionKey) && ApprovalActionKey::normalize($approvable, $actionKey) !== $normalizedAction) {
            throw new InvalidArgumentException('The action and actionKey values do not normalize to the same approval action.');
        }

        return $normalizedAction;
    }

    public function approve(ApprovalStepInstance $stepInstance, int|string|null $userId, ?string $comment = null, bool $force = false): void
    {
        if ($force) {
            throw new AuthorizationException(__('filament-action-approvals::approval.actions.unauthorized'));
        }

        $this->approveStep($stepInstance, $userId, $comment, force: false);
    }

    public function approveForSystem(ApprovalStepInstance $stepInstance, ?string $comment = null): void
    {
        $this->approveStep($stepInstance, null, $comment, force: true);
    }

    private function approveStep(ApprovalStepInstance $stepInstance, int|string|null $userId, ?string $comment = null, bool $force = false): void
    {
        $comment = SensitiveDataRedactor::nullableText($comment);

        DB::transaction(function () use ($stepInstance, $userId, $comment, $force) {
            $stepInstance = $this->lockStepInstance($stepInstance);
            $approval = $this->lockApproval($stepInstance);

            if (! $force && ! $stepInstance->canBeApprovedBy($userId)) {
                throw new AuthorizationException(__('filament-action-approvals::approval.actions.unauthorized'));
            }

            $approval->actions()->create([
                'approval_step_instance_id' => $stepInstance->getKey(),
                'user_id' => $userId,
                'type' => ActionType::Approved,
                'comment' => $comment,
            ]);

            $stepInstance->increment('received_approvals');
            $stepInstance->refresh();

            if ($stepInstance->received_approvals >= $stepInstance->required_approvals) {
                $stepInstance->update([
                    'status' => StepInstanceStatus::Approved,
                    'completed_at' => now(),
                ]);

                $this->afterCommit(function () use ($approval, $stepInstance): void {
                    event(new ApprovalStepCompleted($stepInstance));
                    $this->fireModelCallback($approval->approvable, 'onApprovalStepCompleted', $stepInstance);
                });

                $this->activateNextStep($approval->refresh());
            }
        });
    }

    /**
     * Approve every remaining step of an approval on behalf of a privileged
     * user, recording one Approved action per step and completing the approval.
     *
     * Used to let privileged users apply an approvable action directly: the
     * regular ApprovalCompleted event and onApprovalApproved callback fire once
     * the final step is approved, so consumers apply the mutation through their
     * existing completion hooks instead of a separate bypass path.
     */
    public function autoApprove(Approval $approval, int|string $userId, ?string $comment = null): void
    {
        throw new AuthorizationException(__('filament-action-approvals::approval.actions.unauthorized'));
    }

    public function autoApproveForSystem(Approval $approval, int|string $userId, ?string $comment = null): void
    {
        $this->autoApproveApproval($approval, $userId, $comment);
    }

    private function autoApproveApproval(Approval $approval, int|string $userId, ?string $comment = null): void
    {
        $comment = SensitiveDataRedactor::nullableText($comment);

        DB::transaction(function () use ($approval, $userId, $comment): void {
            $approval = ApprovalModels::approvalQuery()
                ->whereKey($approval->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            while ($approval->status === ApprovalStatus::Pending) {
                $stepInstance = $approval->currentStepInstance();

                if (! $stepInstance) {
                    break;
                }

                $stepInstance = $this->lockStepInstance($stepInstance);
                $stepInstance->setRelation('approval', $approval);

                $approval->actions()->create([
                    'approval_step_instance_id' => $stepInstance->getKey(),
                    'user_id' => $userId,
                    'type' => ActionType::Approved,
                    'comment' => $comment,
                ]);

                $stepInstance->update([
                    'received_approvals' => $stepInstance->required_approvals,
                    'status' => StepInstanceStatus::Approved,
                    'completed_at' => now(),
                ]);

                $this->afterCommit(function () use ($approval, $stepInstance): void {
                    event(new ApprovalStepCompleted($stepInstance));
                    $this->fireModelCallback($approval->approvable, 'onApprovalStepCompleted', $stepInstance);
                });

                $this->activateNextStep($approval->refresh());

                $approval->refresh();
            }
        });
    }

    public function reject(ApprovalStepInstance $stepInstance, int|string|null $userId, ?string $comment = null, bool $force = false): void
    {
        if ($force) {
            throw new AuthorizationException(__('filament-action-approvals::approval.actions.unauthorized'));
        }

        $this->rejectStep($stepInstance, $userId, $comment, force: false);
    }

    public function rejectForSystem(ApprovalStepInstance $stepInstance, ?string $comment = null): void
    {
        $this->rejectStep($stepInstance, null, $comment, force: true);
    }

    private function rejectStep(ApprovalStepInstance $stepInstance, int|string|null $userId, ?string $comment = null, bool $force = false): void
    {
        $comment = SensitiveDataRedactor::nullableText($comment);

        DB::transaction(function () use ($stepInstance, $userId, $comment, $force) {
            $stepInstance = $this->lockStepInstance($stepInstance);
            $approval = $this->lockApproval($stepInstance);

            if (! $force && ! $stepInstance->canBeRejectedBy($userId)) {
                throw new AuthorizationException(__('filament-action-approvals::approval.actions.unauthorized'));
            }

            $approval->actions()->create([
                'approval_step_instance_id' => $stepInstance->getKey(),
                'user_id' => $userId,
                'type' => ActionType::Rejected,
                'comment' => $comment,
            ]);

            $stepInstance->update([
                'status' => StepInstanceStatus::Rejected,
                'completed_at' => now(),
            ]);

            $approval->stepInstances()
                ->where('status', StepInstanceStatus::Pending)
                ->update(['status' => StepInstanceStatus::Skipped]);

            $approval->update([
                'status' => ApprovalStatus::Rejected,
                'completed_at' => now(),
            ]);

            $this->afterCommit(function () use ($approval): void {
                event(new ApprovalRejected($approval));
                $this->fireModelCallback($approval->approvable, 'onApprovalRejected', $approval);

                $this->notifySubmitter($approval, ApprovalRejectedNotification::class);
            });
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function comment(Approval $approval, int|string $userId, string $comment, ?ApprovalStepInstance $stepInstance = null): void
    {
        $comment = SensitiveDataRedactor::text($comment);

        DB::transaction(function () use ($approval, $userId, $comment, $stepInstance): void {
            $approval = ApprovalModels::approvalQuery()
                ->whereKey($approval->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentStep = $stepInstance instanceof ApprovalStepInstance
                ? $this->lockStepInstance($stepInstance)
                : $approval->currentStepInstance();

            if (! $approval->canReceiveCommentsFrom($userId)) {
                throw new AuthorizationException(__('filament-action-approvals::approval.actions.unauthorized'));
            }

            $action = $approval->actions()->create([
                'approval_step_instance_id' => $currentStep?->getKey(),
                'user_id' => $userId,
                'type' => ActionType::Commented,
                'comment' => $comment,
            ]);

            $this->afterCommit(function () use ($approval, $action): void {
                event(new ApprovalCommented($action));
                $this->fireModelCallback($approval->approvable, 'onApprovalCommented', $action);
            });
        });
    }

    public function delegate(ApprovalStepInstance $stepInstance, int|string $fromUserId, int|string $toUserId, ?string $reason = null): void
    {
        $reason = SensitiveDataRedactor::nullableText($reason);

        DB::transaction(function () use ($stepInstance, $fromUserId, $toUserId, $reason) {
            $stepInstance = $this->lockStepInstance($stepInstance);
            $approval = $this->lockApproval($stepInstance);

            if (! $stepInstance->canBeDelegatedBy($fromUserId)) {
                throw new AuthorizationException(__('filament-action-approvals::approval.actions.unauthorized'));
            }

            $stepInstance->delegations()->create([
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'reason' => $reason,
                'delegated_at' => now(),
            ]);

            $approval->actions()->create([
                'approval_step_instance_id' => $stepInstance->getKey(),
                'user_id' => $fromUserId,
                'type' => ActionType::Delegated,
                'comment' => $reason,
                'metadata' => ['delegated_to' => $toUserId],
            ]);

            $this->afterCommit(function () use ($approval, $stepInstance, $fromUserId, $toUserId): void {
                ApprovalRequestedNotification::send($stepInstance, $toUserId);
                event(new ApprovalDelegated($stepInstance, $fromUserId, $toUserId));
                $this->fireModelCallback($approval->approvable, 'onApprovalDelegated', $stepInstance, $fromUserId, $toUserId);
            });
        });
    }

    public function cancel(Approval $approval): void
    {
        DB::transaction(function () use ($approval) {
            $approval->stepInstances()
                ->whereIn('status', [StepInstanceStatus::Pending, StepInstanceStatus::Waiting])
                ->update(['status' => StepInstanceStatus::Skipped]);

            $approval->update([
                'status' => ApprovalStatus::Cancelled,
                'completed_at' => now(),
            ]);

            $this->afterCommit(function () use ($approval): void {
                event(new ApprovalCancelled($approval));
                $this->fireModelCallback($approval->approvable, 'onApprovalCancelled', $approval);

                $this->notifySubmitter($approval, ApprovalCancelledNotification::class);
            });
        });
    }

    public function activateNextStep(Approval $approval): void
    {
        $nextStep = $approval->stepInstances()
            ->where('status', StepInstanceStatus::Pending)
            ->orderBy('order')
            ->first();

        if (! $nextStep) {
            $approval->update([
                'status' => ApprovalStatus::Approved,
                'completed_at' => now(),
            ]);

            $approval = $approval->refresh();

            if (! $this->applyRegisteredActionHandler($approval)) {
                $this->applyApprovedOperation($approval);
            }

            $this->afterCommit(function () use ($approval): void {
                event(new ApprovalCompleted($approval));
                $this->fireModelCallback($approval->approvable, 'onApprovalApproved', $approval);

                $this->notifySubmitter($approval, ApprovalApprovedNotification::class);
            });

            return;
        }

        $slaDeadline = null;
        $step = $nextStep->step;

        if (! $step) {
            return;
        }

        if ($step->sla_hours) {
            $slaDeadline = now()->addHours($step->sla_hours);
        }

        $nextStep->update([
            'status' => StepInstanceStatus::Waiting,
            'activated_at' => now(),
            'sla_deadline' => $slaDeadline,
        ]);

        foreach ($nextStep->assigned_approver_ids as $userId) {
            $this->afterCommit(function () use ($nextStep, $userId): void {
                ApprovalRequestedNotification::send($nextStep, $userId);
            });
        }
    }

    private function applyApprovedOperation(Approval $approval): void
    {
        if (data_get($approval->metadata, 'operation.applied_at') !== null || data_get($approval->metadata, 'crud.applied_at') !== null || data_get($approval->metadata, 'applied_at') !== null) {
            return;
        }

        $operation = $this->resolveApprovalOperation($approval);
        $payload = data_get($approval->metadata, 'payload', []);

        if ($operation === ApprovalActionRegistry::OperationAction) {
            return;
        }

        if (! in_array(ApprovalOperation::fromOperation($operation), [ApprovalOperation::Update, ApprovalOperation::Delete, ApprovalOperation::Restore, ApprovalOperation::ForceDelete], true) || ! is_array($payload)) {
            throw ValidationException::withMessages([
                'approval' => __('filament-action-approvals::approval.actions.apply_failed'),
            ]);
        }

        $approvable = $approval->approvable;

        if (! is_object($approvable) || ! method_exists($approvable, 'applyApprovedOperation')) {
            throw ValidationException::withMessages([
                'approval' => __('filament-action-approvals::approval.actions.apply_failed'),
            ]);
        }

        /** @var array<string, mixed> $payload */
        $applicablePayload = Arr::except($payload, [
            'changed_fields',
            'approval_payload_diff',
        ]);

        $approvable->applyApprovedOperation($operation, $applicablePayload);

        $metadata = $approval->metadata ?? [];
        $appliedAt = now()->toISOString();

        data_set($metadata, 'operation.applied_at', $appliedAt);
        data_set($metadata, 'applied_at', $appliedAt);
        data_set($metadata, 'applied_via', 'operation');

        $approval->forceFill(['metadata' => $metadata])->save();
    }

    private function applyRegisteredActionHandler(Approval $approval): bool
    {
        $operation = $this->resolveApprovalOperation($approval);
        $approvable = $this->resolveApprovableForApply($approval);
        $handler = app(ApprovalActionRegistry::class)->resolveApplyHandler(
            $approval,
            $approvable,
            $approval->submittedActionKey(),
            $operation,
        );

        if ($handler === null) {
            return false;
        }

        if (data_get($approval->metadata, 'applied_at') !== null || data_get($approval->metadata, 'apply_failed_at') !== null) {
            return true;
        }

        if (! $approvable instanceof Model) {
            $this->markRegisteredHandlerApplyFailed($approval, ValidationException::withMessages([
                'approval' => __('filament-action-approvals::approval.actions.apply_failed'),
            ]));

            return true;
        }

        $payload = data_get($approval->metadata, 'payload', []);

        if (! is_array($payload)) {
            $this->markRegisteredHandlerApplyFailed($approval, ValidationException::withMessages([
                'approval' => __('filament-action-approvals::approval.actions.apply_failed'),
            ]));

            return true;
        }

        try {
            /** @var array<string, mixed> $payload */
            $handler($approvable, $approval, $payload, $this->resolveApprovedByUserId($approval));
        } catch (Throwable $exception) {
            $this->markRegisteredHandlerApplyFailed($approval, $exception);

            return true;
        }

        $this->markRegisteredHandlerApplySucceeded($approval);

        return true;
    }

    private function resolveApprovalOperation(Approval $approval): string
    {
        $operation = data_get($approval->metadata, 'operation');

        if (is_string($operation) && filled($operation)) {
            return $operation;
        }

        if (is_array($operation)) {
            $name = data_get($operation, 'name');

            if (is_string($name) && filled($name)) {
                return $name;
            }
        }

        $crudOperation = data_get($approval->metadata, 'crud.operation');

        return is_string($crudOperation) && filled($crudOperation)
            ? $crudOperation
            : ApprovalActionRegistry::OperationAction;
    }

    private function resolveApprovableForApply(Approval $approval): ?Model
    {
        $approvable = $approval->approvable;

        if ($approvable instanceof Model) {
            return $approvable;
        }

        $modelClass = Relation::getMorphedModel($approval->approvable_type) ?? $approval->approvable_type;

        if (! is_a($modelClass, Model::class, true)) {
            return null;
        }

        /** @var class-string<Model> $modelClass */
        $query = $modelClass::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        $approvable = $query->whereKey($approval->approvable_id)->first();

        return $approvable instanceof Model ? $approvable : null;
    }

    private function resolveApprovedByUserId(Approval $approval): int|string|null
    {
        return $approval->actions()
            ->where('type', ActionType::Approved)
            ->latest()
            ->value('user_id');
    }

    private function markRegisteredHandlerApplySucceeded(Approval $approval): void
    {
        $approval->refresh();

        $metadata = $approval->metadata ?? [];
        $metadata['applied_at'] = data_get($metadata, 'applied_at') ?? now()->toISOString();
        $metadata['applied_via'] = data_get($metadata, 'applied_via') ?? 'handler';

        Arr::forget($metadata, ['apply_failed_at', 'apply_failed_reason', 'apply_failed_exception']);

        $approval->forceFill(['metadata' => $metadata])->save();
    }

    private function markRegisteredHandlerApplyFailed(Approval $approval, Throwable $exception): void
    {
        $approval->refresh();

        $metadata = $approval->metadata ?? [];

        if (data_get($metadata, 'applied_at') !== null) {
            return;
        }

        $metadata['apply_failed_at'] = now()->toISOString();
        $metadata['apply_failed_reason'] = __('filament-action-approvals::approval.actions.approved_apply_failed');
        $metadata['apply_failed_exception'] = class_basename($exception);

        $approval->forceFill(['metadata' => $metadata])->save();
    }

    /**
     * @return array{user_id: int|string|null, actor_type: string|null, actor_id: int|string|null}
     */
    protected function resolveSubmitter(Model|int|string|null $submittedBy): array
    {
        if ($submittedBy === null) {
            $submittedBy = CurrentPanelUser::model();
        }

        if ($submittedBy instanceof Model) {
            $actorId = UserModelKey::normalize($submittedBy->getKey());

            return [
                'user_id' => UserModelKey::isConfiguredUserModel($submittedBy) ? $actorId : null,
                'actor_type' => $submittedBy->getMorphClass(),
                'actor_id' => $actorId,
            ];
        }

        $userId = UserModelKey::normalize($submittedBy);

        return [
            'user_id' => $userId,
            'actor_type' => $userId === null ? null : UserModelKey::configuredUserMorphClass(),
            'actor_id' => $userId,
        ];
    }

    protected function shouldBlockConcurrentPendingApproval(Model $approvable, ?string $actionKey): bool
    {
        if (! (bool) config('filament-action-approvals.pending_submissions.block_concurrent', true)) {
            return false;
        }

        return ApprovalModels::approvalQuery()
            ->forApprovable($approvable)
            ->withStatus(ApprovalStatus::Pending)
            ->when(
                filled($actionKey),
                fn ($query) => $query->where(function ($pending) use ($actionKey): void {
                    $pending
                        ->where('metadata->action_key', $actionKey)
                        ->orWhere('action_key', $actionKey)
                        ->orWhereHas('flow', fn ($flow) => $flow->where('action_key', $actionKey));
                }),
            )
            ->exists();
    }

    protected function lockStepInstance(ApprovalStepInstance $stepInstance): ApprovalStepInstance
    {
        return ApprovalModels::stepInstanceQuery()
            ->whereKey($stepInstance->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    protected function lockApproval(ApprovalStepInstance $stepInstance): Approval
    {
        $approval = ApprovalModels::approvalQuery()
            ->whereKey($stepInstance->approval_id)
            ->lockForUpdate()
            ->firstOrFail();

        $stepInstance->setRelation('approval', $approval);

        return $approval;
    }

    protected function afterCommit(callable $callback): void
    {
        if (app()->runningUnitTests()) {
            $callback();

            return;
        }

        DB::afterCommit($callback);
    }

    /**
     * Fire a lifecycle callback on the approvable model if the method exists.
     */
    protected function fireModelCallback(?Model $approvable, string $method, mixed ...$args): void
    {
        if ($approvable && method_exists($approvable, $method)) {
            $approvable->$method(...$args);
        }
    }

    protected function notifySubmitter(Approval $approval, string $notificationClass): void
    {
        if ($approval->submitted_by) {
            $notificationClass::send($approval, $approval->submitted_by);
        }
    }
}
