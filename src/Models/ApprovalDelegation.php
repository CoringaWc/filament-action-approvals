<?php

namespace CoringaWc\FilamentActionApprovals\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;

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

    public function stepInstance(): BelongsTo
    {
        return $this->belongsTo(ApprovalStepInstance::class, 'approval_step_instance_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(FilamentActionApprovalsPlugin::resolveUserModel(), 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(FilamentActionApprovalsPlugin::resolveUserModel(), 'to_user_id');
    }
}
