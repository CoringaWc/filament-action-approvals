<?php

declare(strict_types=1);

namespace Workbench\App\States\Invoice\Transitions;

use Workbench\App\States\Invoice\Paid;

class ToPaid extends InvoiceStateTransition
{
    protected function targetStateClass(): string
    {
        return Paid::class;
    }

    protected function extraAttributes(): array
    {
        return [
            'paid_at' => $this->invoice->paid_at ?? now()->toDateString(),
            'cancelled_at' => null,
        ];
    }
}
