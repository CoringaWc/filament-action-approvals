<?php

namespace CoringaWc\FilamentActionApprovals\Models;

use CoringaWc\FilamentActionApprovals\Enums\ActionType;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $approval_id
 * @property int|null $approval_step_instance_id
 * @property int|null $user_id
 * @property ActionType $type
 * @property string|null $comment
 * @property array<string, mixed>|null $metadata
 * @property-read Approval $approval
 * @property-read ApprovalStepInstance|null $stepInstance
 * @property-read Model|null $user
 */
class ApprovalAction extends Model
{
    protected $fillable = [
        'approval_id',
        'approval_step_instance_id',
        'user_id',
        'type',
        'comment',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => ActionType::class,
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
     * @return BelongsTo<ApprovalStepInstance, $this>
     */
    public function stepInstance(): BelongsTo
    {
        return $this->belongsTo(ApprovalStepInstance::class, 'approval_step_instance_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();

        return $this->belongsTo($userModel);
    }
}
