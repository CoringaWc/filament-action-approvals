<?php

namespace CoringaWc\FilamentActionApprovals\Contracts;

use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;

interface EscalationHandler
{
    /**
     * Handle escalation for a breached step instance.
     */
    public function handle(ApprovalStepInstance $stepInstance): void;
}
