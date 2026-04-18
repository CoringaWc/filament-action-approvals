<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\PurchaseOrders\Pages;

use Filament\Resources\Pages\EditRecord;
use Workbench\App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...static::getResource()::getApprovalHeaderActions(),
        ];
    }
}
