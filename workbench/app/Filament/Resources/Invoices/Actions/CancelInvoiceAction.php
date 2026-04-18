<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Invoices\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Workbench\App\Models\Invoice;
use Workbench\App\States\Invoice\Cancelled;

class CancelInvoiceAction
{
    public static function make(): Action
    {
        return Action::make('cancelInvoice')
            ->label(__('workbench::workbench.resources.invoices.actions.cancel'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('workbench::workbench.resources.invoices.actions.cancel_modal_heading'))
            ->modalDescription(__('workbench::workbench.resources.invoices.actions.cancel_modal_description'))
            ->visible(fn (Invoice $record): bool => self::isVisible($record))
            ->action(function (Invoice $record): void {
                $result = $record->transitionWithApproval(
                    stateAttribute: 'status',
                    toStateClass: Cancelled::class,
                    submittedBy: auth()->id(),
                );

                $notification = Notification::make()
                    ->title($result->pendingApproval
                        ? __('workbench::workbench.resources.invoices.notifications.cancel_pending_title')
                        : __('workbench::workbench.resources.invoices.notifications.cancel_success_title'))
                    ->body($result->pendingApproval
                        ? __('workbench::workbench.resources.invoices.notifications.cancel_pending_body')
                        : __('workbench::workbench.resources.invoices.notifications.cancel_success_body'));

                if ($result->pendingApproval) {
                    $notification->warning();
                } else {
                    $notification->danger();
                }

                $notification->send();
            });
    }

    public static function isVisible(Invoice $record): bool
    {
        if (! $record->status->canTransitionTo(Cancelled::class)) {
            return false;
        }

        return ! $record->hasPendingApprovalForAction(
            Invoice::stateApprovalActionKey($record->status::class, Cancelled::class),
        );
    }
}
