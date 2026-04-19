<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Models;

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $approval_step_instance_id
 * @property int $from_user_id
 * @property int $to_user_id
 * @property string|null $reason
 * @property Carbon|null $delegated_at
 * @property-read ApprovalStepInstance $stepInstance
 * @property-read Model $fromUser
 * @property-read Model $toUser
 */
class ApprovalDelegation extends Model
{
    protected $fillable = [
        'approval_step_instance_id',
        'from_user_id',
        'to_user_id',
        'reason',
        'delegated_at',
    ];

    protected function casts(): array
    {
        return [
            'delegated_at' => 'datetime',
        ];
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
    public function fromUser(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();

        return $this->belongsTo($userModel, 'from_user_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function toUser(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();

        return $this->belongsTo($userModel, 'to_user_id');
    }
}
