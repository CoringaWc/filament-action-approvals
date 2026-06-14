<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Tests\Fixtures\Models;

use CoringaWc\FilamentActionApprovals\Models\Approval;

class CustomApproval extends Approval
{
    public function customMarker(): string
    {
        return 'custom-approval';
    }
}
