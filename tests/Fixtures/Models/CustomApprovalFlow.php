<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Tests\Fixtures\Models;

use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;

class CustomApprovalFlow extends ApprovalFlow
{
    public function customMarker(): string
    {
        return 'custom-approval-flow';
    }
}
