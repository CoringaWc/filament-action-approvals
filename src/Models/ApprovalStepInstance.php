<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Models;

use CoringaWc\FilamentActionApprovals\Enums\ActionType;
use CoringaWc\FilamentActionApprovals\Enums\StepInstanceStatus;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $approval_id
 * @property int $approval_step_id
 * @property int $order
 * @property StepType $type
 * @property StepInstanceStatus $status
 * @property int $required_approvals
 * @property int $received_approvals
 * @property list<int|string> $assigned_approver_ids
 * @property Carbon|null $activated_at
 * @property Carbon|null $sla_deadline
 * @property bool $sla_warning_sent
 * @property bool $sla_breached
 * @property Carbon|null $completed_at
 * @property array<string, mixed>|null $metadata
 * @property-read Approval $approval
 * @property-read ApprovalStep|null $step
 * @property-read Collection<int, ApprovalAction> $actions
 * @property-read Collection<int, ApprovalDelegation> $delegations
 */
class ApprovalStepInstance extends Model
{
    protected $fillable = [
        'approval_id',
        'approval_step_id',
        'order',
        'type',
        'status',
        'required_approvals',
        'received_approvals',
        'assigned_approver_ids',
        'activated_at',
        'sla_deadline',
        'sla_warning_sent',
        'sla_breached',
        'completed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => StepType::class,
            'status' => StepInstanceStatus::class,
            'assigned_approver_ids' => 'array',
            'activated_at' => 'datetime',
            'sla_deadline' => 'datetime',
            'completed_at' => 'datetime',
            'sla_warning_sent' => 'boolean',
            'sla_breached' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Approval, $this>
     */
    public function approval(): BelongsTo
    {
        return $this->belongsTo(Approval::class);
    }

    /**
     * @return BelongsTo<ApprovalStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(ApprovalStep::class, 'approval_step_id');
    }

    /**
     * @return HasMany<ApprovalAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class);
    }

    /**
     * @return HasMany<ApprovalDelegation, $this>
     */
    public function delegations(): HasMany
    {
        return $this->hasMany(ApprovalDelegation::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', StepInstanceStatus::Waiting);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAssignedTo(Builder $query, int|string|null $userId): Builder
    {
        if (! is_int($userId) && ! is_string($userId)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $stepInstances) use ($userId): void {
            $stepInstances
                ->whereJsonContains('assigned_approver_ids', $userId)
                ->orWhereHas('delegations', fn (Builder $delegations): Builder => $delegations->where('to_user_id', $userId));
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->waiting()
            ->whereNotNull('sla_deadline')
            ->where('sla_deadline', '<', now());
    }

    public function isWaiting(): bool
    {
        return $this->status === StepInstanceStatus::Waiting;
    }

    public function isOverdue(): bool
    {
        return $this->isWaiting()
            && $this->sla_deadline !== null
            && $this->sla_deadline->isPast();
    }

    public function isAssignedTo(int|string|null $userId): bool
    {
        if (! is_int($userId) && ! is_string($userId)) {
            return false;
        }

        if (in_array($userId, $this->assigned_approver_ids, true)) {
            return true;
        }

        if ($this->relationLoaded('delegations')) {
            return $this->delegations->contains(
                fn (ApprovalDelegation $delegation): bool => (string) $delegation->to_user_id === (string) $userId,
            );
        }

        return $this->delegations()
            ->where('to_user_id', $userId)
            ->exists();
    }

    public function canBeApprovedBy(int|string|null $userId): bool
    {
        return $this->canBeResolvedBy($userId);
    }

    public function canBeRejectedBy(int|string|null $userId): bool
    {
        return $this->canBeResolvedBy($userId);
    }

    public function canReceiveCommentsFrom(int|string|null $userId): bool
    {
        if (! is_int($userId) && ! is_string($userId)) {
            return false;
        }

        if (! $this->isWaiting()) {
            return false;
        }

        if (FilamentActionApprovalsPlugin::isSuperAdmin($userId)) {
            return true;
        }

        return $this->isAssignedTo($userId);
    }

    public function canBeDelegatedBy(int|string|null $userId): bool
    {
        if (! is_int($userId) && ! is_string($userId)) {
            return false;
        }

        if (! $this->isWaiting()) {
            return false;
        }

        if (FilamentActionApprovalsPlugin::isSuperAdmin($userId)) {
            return true;
        }

        return in_array($userId, $this->assigned_approver_ids, true);
    }

    public function canUserAct(int|string $userId): bool
    {
        return $this->isAssignedTo($userId);
    }

    public function hasUserActed(int|string $userId): bool
    {
        return $this->actions()
            ->where('user_id', $userId)
            ->whereIn('type', [ActionType::Approved, ActionType::Rejected])
            ->exists();
    }

    protected function canBeResolvedBy(int|string|null $userId): bool
    {
        if (! is_int($userId) && ! is_string($userId)) {
            return false;
        }

        if (! $this->isWaiting()) {
            return false;
        }

        if (FilamentActionApprovalsPlugin::isSuperAdmin($userId)) {
            return ! $this->hasUserActed($userId);
        }

        return $this->isAssignedTo($userId)
            && ! $this->hasUserActed($userId);
    }
}
