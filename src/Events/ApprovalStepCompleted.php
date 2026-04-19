<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Events;

use CoringaWc\FilamentActionApprovals\Concerns\BroadcastsConditionally;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class ApprovalStepCompleted implements ShouldBroadcast
{
    use BroadcastsConditionally;
    use Dispatchable;

    public function __construct(
        public readonly ApprovalStepInstance $stepInstance,
    ) {}

    protected function broadcastConfigKey(): string
    {
        return 'step_completed';
    }
}
