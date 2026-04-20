<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Widgets;

use Illuminate\Contracts\Support\Htmlable;

class PendingApprovalsTable extends PendingApprovalsWidget
{
    protected function getTableHeading(): string|Htmlable|null
    {
        return '';
    }
}
