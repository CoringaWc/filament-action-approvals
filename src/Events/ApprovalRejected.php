<?php

namespace CoringaWc\FilamentActionApprovals\Events;

use CoringaWc\FilamentActionApprovals\Models\Approval;
use Illuminate\Foundation\Events\Dispatchable;

class ApprovalRejected
{
    use Dispatchable;

    public function __construct(
        public readonly Approval $approval,
    ) {}
}
