<?php

declare(strict_types=1);

namespace Workbench\App\States\Invoice\Transitions;

use Workbench\App\States\Invoice\Cancelled;

class ToCancelled extends InvoiceStateTransition
{
    protected function targetStateClass(): string
    {
        return Cancelled::class;
    }

    protected function extraAttributes(): array
    {
        return [
            'cancelled_at' => $this->invoice->cancelled_at ?? now()->toDateString(),
        ];
    }
}
