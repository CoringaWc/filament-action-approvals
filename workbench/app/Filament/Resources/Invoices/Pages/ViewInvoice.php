<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Invoices\Pages;

use Filament\Resources\Pages\ViewRecord;
use Workbench\App\Filament\Resources\Invoices\Actions\AdvanceInvoiceStatusAction;
use Workbench\App\Filament\Resources\Invoices\Actions\CancelInvoiceAction;
use Workbench\App\Filament\Resources\Invoices\InvoiceResource;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AdvanceInvoiceStatusAction::make(),
            CancelInvoiceAction::make(),
            ...static::getResource()::getApprovalResponseHeaderActions(),
        ];
    }
}
