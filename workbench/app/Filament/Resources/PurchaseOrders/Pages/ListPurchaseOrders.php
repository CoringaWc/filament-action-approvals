<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\PurchaseOrders\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Workbench\App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;

class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
