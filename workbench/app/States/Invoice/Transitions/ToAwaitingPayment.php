<?php

declare(strict_types=1);

namespace Workbench\App\States\Invoice\Transitions;

use Workbench\App\States\Invoice\AwaitingPayment;

class ToAwaitingPayment extends InvoiceStateTransition
{
    protected function targetStateClass(): string
    {
        return AwaitingPayment::class;
    }
}
