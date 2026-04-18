<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Invoices\Pages;

use CoringaWc\FilamentActionApprovals\Actions\ApproveAction;
use CoringaWc\FilamentActionApprovals\Actions\CommentAction;
use CoringaWc\FilamentActionApprovals\Actions\DelegateAction;
use CoringaWc\FilamentActionApprovals\Actions\RejectAction;
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
            ApproveAction::make(),
            RejectAction::make(),
            CommentAction::make(),
            DelegateAction::make(),
        ];
    }
}
