<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\PurchaseOrders\Pages;

use CoringaWc\FilamentActionApprovals\Actions\ApproveAction;
use CoringaWc\FilamentActionApprovals\Actions\CommentAction;
use CoringaWc\FilamentActionApprovals\Actions\DelegateAction;
use CoringaWc\FilamentActionApprovals\Actions\RejectAction;
use CoringaWc\FilamentActionApprovals\Actions\SubmitForApprovalAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Workbench\App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Dedicated submit actions per approvable action key
            SubmitForApprovalAction::make('submitPO')
                ->actionKey('submit')
                ->label(__('workbench::workbench.approval_actions.purchase_orders.submit'))
                ->icon(Heroicon::OutlinedPaperAirplane),

            SubmitForApprovalAction::make('cancelPO')
                ->actionKey('cancel')
                ->label(__('workbench::workbench.approval_actions.purchase_orders.cancel'))
                ->icon(Heroicon::OutlinedXMark)
                ->color('danger'),

            // Approval response actions (used by approvers)
            ApproveAction::make(),
            RejectAction::make(),
            CommentAction::make(),
            DelegateAction::make(),
        ];
    }
}
