<?php

declare(strict_types=1);

namespace Workbench\App\States\Invoice\Transitions;

use Workbench\App\States\Invoice\Sent;

class ToSent extends InvoiceStateTransition
{
    protected function targetStateClass(): string
    {
        return Sent::class;
    }

    protected function extraAttributes(): array
    {
        return [
            'sent_at' => $this->invoice->sent_at ?? now()->toDateString(),
            'cancelled_at' => null,
        ];
    }
}
