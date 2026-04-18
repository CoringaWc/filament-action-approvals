<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\PurchaseOrders\Pages;

use Filament\Resources\Pages\CreateRecord;
use Workbench\App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;
}
