<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Tests\Fixtures\Models;

use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;

class CustomApprovalStepInstance extends ApprovalStepInstance
{
    public function customMarker(): string
    {
        return 'custom-approval-step-instance';
    }
}
