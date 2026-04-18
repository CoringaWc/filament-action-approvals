<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Invoices\Pages;

use Filament\Resources\Pages\CreateRecord;
use Workbench\App\Filament\Resources\Invoices\InvoiceResource;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;
}
