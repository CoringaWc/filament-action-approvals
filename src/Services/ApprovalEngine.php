<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Services;

use CoringaWc\FilamentActionApprovals\Enums\ActionType;
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
use CoringaWc\FilamentActionApprovals\Notifications\ApprovalRejectedNotification;
use CoringaWc\FilamentActionApprovals\Notifications\ApprovalRequestedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ApprovalEngine
{
    public function submit(Model $approvable, ?ApprovalFlow $flow = null, ?int $submittedBy = null, ?string $actionKey = null): Approval
    {
        $flow ??= ApprovalFlow::findSubmissionFlowForModel($approvable, $actionKey);
        $submittedBy ??= auth()->id();

        if (is_string($submittedBy) && ctype_digit($submittedBy)) {
            $submittedBy = (int) $submittedBy;
        }

        if (! is_int($submittedBy)) {
            $submittedBy = null;
        }

        if (! $flow) {
            throw (new ModelNotFoundException)->setModel(ApprovalFlow::class);
        }

        return DB::transaction(function () use ($approvable, $flow, $submittedBy) {
            $approval = Approval::create([
                'approval_flow_id' => $flow->getKey(),
                'approvable_type' => $approvable->getMorphClass(),
                'approvable_id' => $approvable->getKey(),
                'status' => ApprovalStatus::Pending,
                'submitted_by' => $submittedBy,
                'submitted_at' => now(),
            ]);

            foreach ($flow->steps as $step) {
                $approverIds = $step->resolveApproverIds($approvable);

                ApprovalStepInstance::create([
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
                'user_id' => $submittedBy,
                'type' => ActionType::Submitted,
            ]);

            $this->activateNextStep($approval);

            event(new ApprovalSubmitted($approval));
            $this->fireModelCallback($approvable, 'onApprovalSubmitted', $approval);

            return $approval;
        });
    }

    public function approve(ApprovalStepInstance $stepInstance, int $userId, ?string $comment = null): void
    {
        DB::transaction(function () use ($stepInstance, $userId, $comment) {
            $approval = $stepInstance->approval;

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

                event(new ApprovalStepCompleted($stepInstance));
                $this->fireModelCallback($approval->approvable, 'onApprovalStepCompleted', $stepInstance);

                $this->activateNextStep($approval->refresh());
            }
        });
    }

    public function reject(ApprovalStepInstance $stepInstance, int $userId, ?string $comment = null): void
    {
        DB::transaction(function () use ($stepInstance, $userId, $comment) {
            $approval = $stepInstance->approval;

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

            event(new ApprovalRejected($approval));
            $this->fireModelCallback($approval->approvable, 'onApprovalRejected', $approval);

            $this->notifySubmitter($approval, ApprovalRejectedNotification::class);
        });
    }

    public function comment(Approval $approval, int $userId, string $comment, ?ApprovalStepInstance $stepInstance = null): void
    {
        $action = $approval->actions()->create([
            'approval_step_instance_id' => $stepInstance?->getKey(),
            'user_id' => $userId,
            'type' => ActionType::Commented,
            'comment' => $comment,
        ]);

        event(new ApprovalCommented($action));
        $this->fireModelCallback($approval->approvable, 'onApprovalCommented', $action);
    }

    public function delegate(ApprovalStepInstance $stepInstance, int $fromUserId, int $toUserId, ?string $reason = null): void
    {
        DB::transaction(function () use ($stepInstance, $fromUserId, $toUserId, $reason) {
            $stepInstance->delegations()->create([
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'reason' => $reason,
                'delegated_at' => now(),
            ]);

            $stepInstance->approval->actions()->create([
                'approval_step_instance_id' => $stepInstance->getKey(),
                'user_id' => $fromUserId,
                'type' => ActionType::Delegated,
                'comment' => $reason,
                'metadata' => ['delegated_to' => $toUserId],
            ]);

            ApprovalRequestedNotification::send($stepInstance, $toUserId);
            event(new ApprovalDelegated($stepInstance, $fromUserId, $toUserId));
            $this->fireModelCallback($stepInstance->approval->approvable, 'onApprovalDelegated', $stepInstance, $fromUserId, $toUserId);
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

            event(new ApprovalCancelled($approval));
            $this->fireModelCallback($approval->approvable, 'onApprovalCancelled', $approval);
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

            event(new ApprovalCompleted($approval));
            $this->fireModelCallback($approval->approvable, 'onApprovalApproved', $approval);

            $this->notifySubmitter($approval, ApprovalApprovedNotification::class);

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
            ApprovalRequestedNotification::send($nextStep, $userId);
        }
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
