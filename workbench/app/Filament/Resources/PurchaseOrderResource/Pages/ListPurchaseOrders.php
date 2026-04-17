<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\PurchaseOrderResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Workbench\App\Filament\Resources\PurchaseOrderResource;

class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;
}
