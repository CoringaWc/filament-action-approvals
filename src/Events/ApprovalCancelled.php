<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Events;

use CoringaWc\FilamentActionApprovals\Concerns\BroadcastsConditionally;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class ApprovalCancelled implements ShouldBroadcast
{
    use BroadcastsConditionally;
    use Dispatchable;

    public function __construct(
        public readonly Approval $approval,
    ) {}

    protected function broadcastConfigKey(): string
    {
        return 'cancelled';
    }
}
