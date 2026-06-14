<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Tests\Fixtures\Models;

use CoringaWc\FilamentActionApprovals\Models\ApprovalAction;

class CustomApprovalAction extends ApprovalAction
{
    public function customMarker(): string
    {
        return 'custom-approval-action';
    }
}
