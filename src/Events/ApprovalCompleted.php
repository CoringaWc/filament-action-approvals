<?php

namespace CoringaWc\FilamentActionApprovals\Events;

use Illuminate\Foundation\Events\Dispatchable;
use CoringaWc\FilamentActionApprovals\Models\Approval;

class ApprovalCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly Approval $approval,
    ) {}
}
