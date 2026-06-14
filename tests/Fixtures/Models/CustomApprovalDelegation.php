<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Tests\Fixtures\Models;

use CoringaWc\FilamentActionApprovals\Models\ApprovalDelegation;

class CustomApprovalDelegation extends ApprovalDelegation
{
    public function customMarker(): string
    {
        return 'custom-approval-delegation';
    }
}
