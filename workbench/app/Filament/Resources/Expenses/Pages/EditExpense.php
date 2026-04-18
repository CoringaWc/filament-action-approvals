<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Expenses\Pages;

use CoringaWc\FilamentActionApprovals\Actions\ApproveAction;
use CoringaWc\FilamentActionApprovals\Actions\CommentAction;
use CoringaWc\FilamentActionApprovals\Actions\DelegateAction;
use CoringaWc\FilamentActionApprovals\Actions\RejectAction;
use CoringaWc\FilamentActionApprovals\Actions\SubmitForApprovalAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Workbench\App\Filament\Resources\Expenses\ExpenseResource;

class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SubmitForApprovalAction::make('submitExpense')
                ->actionKey('submit')
                ->label(__('workbench::workbench.approval_actions.expenses.submit'))
                ->icon(Heroicon::OutlinedPaperAirplane),

            SubmitForApprovalAction::make('reimburseExpense')
                ->actionKey('reimburse')
                ->label(__('workbench::workbench.approval_actions.expenses.reimburse'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success'),

            ApproveAction::make(),
            RejectAction::make(),
            CommentAction::make(),
            DelegateAction::make(),
        ];
    }
}
