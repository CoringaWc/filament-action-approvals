<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Invoices\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Workbench\App\Models\Invoice;
use Workbench\App\States\Invoice\AwaitingPayment;
use Workbench\App\States\Invoice\InvoiceState;
use Workbench\App\States\Invoice\Paid;
use Workbench\App\States\Invoice\Sent;

class AdvanceInvoiceStatusAction
{
    public static function make(): Action
    {
        return Action::make('advanceInvoiceStatus')
            ->label(fn (Invoice $record): string => __('workbench::workbench.resources.invoices.actions.advance_to', [
                'status' => self::getNextLabel($record),
            ]))
            ->icon(Heroicon::OutlinedArrowRight)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(fn (Invoice $record): string => __('workbench::workbench.resources.invoices.actions.advance_modal_heading', [
                'status' => self::getNextLabel($record),
            ]))
            ->modalDescription(__('workbench::workbench.resources.invoices.actions.advance_modal_description'))
            ->visible(fn (Invoice $record): bool => self::isVisible($record))
            ->action(function (Invoice $record): void {
                $nextStateClass = self::getNextStateClass($record);

                if ($nextStateClass === null) {
                    return;
                }

                $result = $record->transitionWithApproval(
                    stateAttribute: 'status',
                    toStateClass: $nextStateClass,
                    submittedBy: auth()->id(),
                );

                $notification = Notification::make()
                    ->title($result->pendingApproval
                        ? __('workbench::workbench.resources.invoices.notifications.transition_pending_title')
                        : __('workbench::workbench.resources.invoices.notifications.transition_success_title'))
                    ->body($result->pendingApproval
                        ? __('workbench::workbench.resources.invoices.notifications.transition_pending_body')
                        : __('workbench::workbench.resources.invoices.notifications.transition_success_body'));

                if ($result->pendingApproval) {
                    $notification->warning();
                } else {
                    $notification->success();
                }

                $notification->send();
            });
    }

    /**
     * @return class-string<InvoiceState>|null
     */
    public static function getNextStateClass(Invoice $record): ?string
    {
        return match (true) {
            $record->isIssuing() => Sent::class,
            $record->isSent() => AwaitingPayment::class,
            $record->isAwaitingPayment() => Paid::class,
            default => null,
        };
    }

    public static function isVisible(Invoice $record): bool
    {
        $nextStateClass = self::getNextStateClass($record);

        if ($nextStateClass === null || ! $record->status->canTransitionTo($nextStateClass)) {
            return false;
        }

        return ! $record->hasPendingApprovalForAction(
            Invoice::stateApprovalActionKey($record->status::class, $nextStateClass),
        );
    }

    private static function getNextLabel(Invoice $record): string
    {
        $nextStateClass = self::getNextStateClass($record);

        if ($nextStateClass === null) {
            return __('workbench::workbench.resources.invoices.actions.advance_default');
        }

        return (new $nextStateClass($record))->getLabel();
    }
}
