<?php

declare(strict_types=1);

namespace Workbench\App\States\Invoice;

use Workbench\App\Enums\InvoiceStatusEnum;

class AwaitingPayment extends InvoiceState
{
    public static string $name = 'AwaitingPayment';

    public function toEnum(): InvoiceStatusEnum
    {
        return InvoiceStatusEnum::AwaitingPayment;
    }
}
