<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Tests\Fixtures\Models;

use CoringaWc\FilamentActionApprovals\Models\ApprovalStep;

class CustomApprovalStep extends ApprovalStep
{
    public function customMarker(): string
    {
        return 'custom-approval-step';
    }
}
