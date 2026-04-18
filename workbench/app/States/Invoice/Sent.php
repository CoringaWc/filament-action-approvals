<?php

declare(strict_types=1);

namespace Workbench\App\States\Invoice;

use Workbench\App\Enums\InvoiceStatusEnum;

class Sent extends InvoiceState
{
    public static string $name = 'Sent';

    public function toEnum(): InvoiceStatusEnum
    {
        return InvoiceStatusEnum::Sent;
    }
}
