<?php

namespace CoringaWc\FilamentActionApprovals\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use CoringaWc\FilamentActionApprovals\Enums\ActionType;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;

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

    public function approval(): BelongsTo
    {
        return $this->belongsTo(Approval::class);
    }

    public function stepInstance(): BelongsTo
    {
        return $this->belongsTo(ApprovalStepInstance::class, 'approval_step_instance_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(FilamentActionApprovalsPlugin::resolveUserModel());
    }
}
