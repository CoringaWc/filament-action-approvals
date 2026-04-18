<?php

declare(strict_types=1);

namespace Workbench\App\States\Invoice;

use Workbench\App\Enums\InvoiceStatusEnum;

class Paid extends InvoiceState
{
    public static string $name = 'Paid';

    public function toEnum(): InvoiceStatusEnum
    {
        return InvoiceStatusEnum::Paid;
    }
}
