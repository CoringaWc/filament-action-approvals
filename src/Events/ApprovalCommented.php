<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Events;

use CoringaWc\FilamentActionApprovals\Concerns\BroadcastsConditionally;
use CoringaWc\FilamentActionApprovals\Models\ApprovalAction;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class ApprovalCommented implements ShouldBroadcast
{
    use BroadcastsConditionally;
    use Dispatchable;

    public function __construct(
        public readonly ApprovalAction $action,
    ) {}

    protected function broadcastConfigKey(): string
    {
        return 'commented';
    }
}
