<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Models;

use CoringaWc\FilamentActionApprovals\Enums\ActionType;
use CoringaWc\FilamentActionApprovals\Enums\StepInstanceStatus;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
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
 * @property list<int> $assigned_approver_ids
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

    public function canUserAct(int $userId): bool
    {
        if (in_array($userId, $this->assigned_approver_ids)) {
            return true;
        }

        return $this->delegations()
            ->where('to_user_id', $userId)
            ->exists();
    }

    public function hasUserActed(int $userId): bool
    {
        return $this->actions()
            ->where('user_id', $userId)
            ->whereIn('type', [ActionType::Approved, ActionType::Rejected])
            ->exists();
    }
}
