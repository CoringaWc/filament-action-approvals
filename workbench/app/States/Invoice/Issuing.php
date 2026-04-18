<?php

declare(strict_types=1);

namespace Workbench\App\States\Invoice;

use Workbench\App\Enums\InvoiceStatusEnum;

class Issuing extends InvoiceState
{
    public static string $name = 'Issuing';

    public function toEnum(): InvoiceStatusEnum
    {
        return InvoiceStatusEnum::Issuing;
    }
}
