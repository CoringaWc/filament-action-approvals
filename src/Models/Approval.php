<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Models;

use CoringaWc\FilamentActionApprovals\Enums\ActionType;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Enums\StepInstanceStatus;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $approval_flow_id
 * @property string $approvable_type
 * @property int|string $approvable_id
 * @property ApprovalStatus $status
 * @property int|null $submitted_by
 * @property Carbon|null $submitted_at
 * @property Carbon|null $completed_at
 * @property array<string, mixed>|null $metadata
 * @property-read Model|null $approvable
 * @property-read ApprovalFlow|null $flow
 * @property-read Model|null $submitter
 * @property-read Collection<int, ApprovalStepInstance> $stepInstances
 * @property-read Collection<int, ApprovalAction> $actions
 */
class Approval extends Model
{
    protected $fillable = [
        'approval_flow_id',
        'approvable_type',
        'approvable_id',
        'status',
        'submitted_by',
        'submitted_at',
        'completed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<ApprovalFlow, $this>
     */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function submitter(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();

        return $this->belongsTo($userModel, 'submitted_by');
    }

    /**
     * @return HasMany<ApprovalStepInstance, $this>
     */
    public function stepInstances(): HasMany
    {
        return $this->hasMany(ApprovalStepInstance::class)->orderBy('order');
    }

    /**
     * @return HasMany<ApprovalAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class)->orderByDesc('created_at');
    }

    public function currentStepInstance(): ?ApprovalStepInstance
    {
        return $this->stepInstances()
            ->where('status', StepInstanceStatus::Waiting)
            ->first();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForApprovable(Builder $query, Model $approvable): Builder
    {
        return $query
            ->where('approvable_type', $approvable->getMorphClass())
            ->where('approvable_id', $approvable->getKey());
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForApprovableType(Builder $query, string $approvableType): Builder
    {
        return $query->where('approvable_type', $approvableType);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithStatus(Builder $query, ApprovalStatus|string|null $status): Builder
    {
        if ($status === null || $status === '') {
            return $query;
        }

        return $query->where('status', $status instanceof ApprovalStatus ? $status->value : $status);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSubmittedBy(Builder $query, int|string|null $userId): Builder
    {
        if (! is_int($userId) && ! is_string($userId)) {
            return $query;
        }

        return $query->where('submitted_by', $userId);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAwaitingUserAction(Builder $query, int|string|null $userId): Builder
    {
        if (! is_int($userId) && ! is_string($userId)) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->withStatus(ApprovalStatus::Pending)
            ->whereHas('stepInstances', function (Builder $stepInstances) use ($userId): void {
                /** @var Builder<ApprovalStepInstance> $stepInstances */
                $stepInstances
                    ->waiting()
                    ->assignedTo($userId);
            });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithOperationalRelations(Builder $query): Builder
    {
        return $query->with([
            'approvable',
            'flow',
            'submitter',
            'stepInstances.step',
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === ApprovalStatus::Pending;
    }

    public function isAwaitingUserAction(int|string|null $userId): bool
    {
        if (! is_int($userId) && ! is_string($userId)) {
            return false;
        }

        if (! $this->isPending()) {
            return false;
        }

        $currentStep = $this->relationLoaded('stepInstances')
            ? $this->stepInstances->firstWhere('status', StepInstanceStatus::Waiting)
            : $this->currentStepInstance();

        if (! $currentStep instanceof ApprovalStepInstance) {
            return false;
        }

        return $currentStep->isAssignedTo($userId);
    }

    public function canBeApprovedBy(int|string|null $userId): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        return $this->currentStepInstance()?->canBeApprovedBy($userId) ?? false;
    }

    public function canBeRejectedBy(int|string|null $userId): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        return $this->currentStepInstance()?->canBeRejectedBy($userId) ?? false;
    }

    public function canReceiveCommentsFrom(int|string|null $userId): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        return $this->currentStepInstance()?->canReceiveCommentsFrom($userId) ?? false;
    }

    public function canBeDelegatedBy(int|string|null $userId): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        return $this->currentStepInstance()?->canBeDelegatedBy($userId) ?? false;
    }

    public function submittedActionKey(): ?string
    {
        $actionKey = data_get($this->metadata, 'action_key');

        if (is_string($actionKey) && filled($actionKey)) {
            return $actionKey;
        }

        $submittedActionKey = $this->relationLoaded('actions')
            ? data_get(
                $this->actions->firstWhere('type', ActionType::Submitted),
                'metadata.action_key',
            )
            : data_get(
                $this->actions()
                    ->where('type', ActionType::Submitted)
                    ->latest()
                    ->first(),
                'metadata.action_key',
            );

        if (is_string($submittedActionKey) && filled($submittedActionKey)) {
            return $submittedActionKey;
        }

        $flowActionKey = $this->flow?->action_key;

        return is_string($flowActionKey) && filled($flowActionKey)
            ? $flowActionKey
            : null;
    }

    /**
     * Get the rejection reason (comment) from the latest rejection action.
     */
    public function latestRejectionReason(): ?string
    {
        if ($this->status !== ApprovalStatus::Rejected) {
            return null;
        }

        /** @var ?ApprovalAction $rejectionAction */
        $rejectionAction = $this->actions()
            ->where('type', ActionType::Rejected)
            ->latest()
            ->first();

        return $rejectionAction?->comment;
    }

    /**
     * Get action_keys that already have a completed (non-pending) approval.
     *
     * Useful to hide submit buttons for actions that were already fully processed.
     *
     * @return list<string>
     */
    public static function completedActionKeysFor(Model $model): array
    {
        return array_values(
            static::query()
                ->where('approvable_type', $model->getMorphClass())
                ->where('approvable_id', $model->getKey())
                ->whereIn('status', [ApprovalStatus::Approved, ApprovalStatus::Rejected])
                ->get()
                ->map(fn (Approval $approval): ?string => $approval->submittedActionKey())
                ->filter()
                ->unique()
                ->map(fn (mixed $key): string => (string) $key)
                ->all(),
        );
    }
}
