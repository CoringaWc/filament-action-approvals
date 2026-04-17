<?php

namespace CoringaWc\FilamentActionApprovals\Events;

use Illuminate\Foundation\Events\Dispatchable;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;

class ApprovalStepCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly ApprovalStepInstance $stepInstance,
    ) {}
}
