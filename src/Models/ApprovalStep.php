<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Models;

use CoringaWc\FilamentActionApprovals\Contracts\ApproverResolver;
use CoringaWc\FilamentActionApprovals\Enums\EscalationAction;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $approval_flow_id
 * @property string $name
 * @property int $order
 * @property StepType $type
 * @property class-string<ApproverResolver> $approver_resolver
 * @property array<string, mixed>|null $approver_config
 * @property int $required_approvals
 * @property int|null $sla_hours
 * @property EscalationAction|null $escalation_action
 * @property array<string, mixed>|null $escalation_config
 * @property array<string, mixed>|null $metadata
 * @property-read ApprovalFlow $flow
 * @property-read Collection<int, ApprovalStepInstance> $instances
 */
class ApprovalStep extends Model
{
    protected $fillable = [
        'approval_flow_id',
        'name',
        'order',
        'type',
        'approver_resolver',
        'approver_config',
        'required_approvals',
        'sla_hours',
        'escalation_action',
        'escalation_config',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => StepType::class,
            'approver_config' => 'array',
            'escalation_action' => EscalationAction::class,
            'escalation_config' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ApprovalFlow, $this>
     */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }

    /**
     * @return HasMany<ApprovalStepInstance, $this>
     */
    public function instances(): HasMany
    {
        return $this->hasMany(ApprovalStepInstance::class);
    }

    /**
     * Resolve the approver IDs using the configured resolver.
     *
     * @return list<int|string>
     */
    public function resolveApproverIds(Model $approvable): array
    {
        /** @var ApproverResolver $resolver */
        $resolver = app($this->approver_resolver);

        return $resolver->resolve($this->approver_config ?? [], $approvable);
    }
}
