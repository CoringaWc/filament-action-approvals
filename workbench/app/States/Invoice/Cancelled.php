<?php

declare(strict_types=1);

namespace Workbench\App\States\Invoice;

use Workbench\App\Enums\InvoiceStatusEnum;

class Cancelled extends InvoiceState
{
    public static string $name = 'Cancelled';

    public function toEnum(): InvoiceStatusEnum
    {
        return InvoiceStatusEnum::Cancelled;
    }
}
