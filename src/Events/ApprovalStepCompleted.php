<?php

namespace CoringaWc\FilamentActionApprovals\Events;

use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use Illuminate\Foundation\Events\Dispatchable;

class ApprovalStepCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly ApprovalStepInstance $stepInstance,
    ) {}
}
