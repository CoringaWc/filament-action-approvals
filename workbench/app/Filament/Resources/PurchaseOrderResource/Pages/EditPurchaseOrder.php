<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\PurchaseOrderResource\Pages;

use CoringaWc\FilamentActionApprovals\Concerns\HasApprovalsResource;
use Filament\Resources\Pages\EditRecord;
use Workbench\App\Filament\Resources\PurchaseOrderResource;

class EditPurchaseOrder extends EditRecord
{
    use HasApprovalsResource;

    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...static::getApprovalHeaderActions(),
        ];
    }
}
