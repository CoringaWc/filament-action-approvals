<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Notifications;

use CoringaWc\FilamentActionApprovals\Enums\ApprovalNotificationEvent;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Support\ApprovalNotificationContext;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ApprovalRequestedNotification
{
    public static function send(ApprovalStepInstance $stepInstance, int|string $userId): void
    {
        if (! config('filament-action-approvals.notifications.database', true)) {
            return;
        }

        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();
        $recipient = $userModel::find($userId);

        if (! $recipient) {
            return;
        }

        $approval = $stepInstance->approval;
        $step = $stepInstance->step;
        $stepName = $step ? $step->name : __('filament-action-approvals::approval.relation_manager.not_available');

        $notification = Notification::make()
            ->title(__('filament-action-approvals::approval.notifications.requested_title', ['step' => $stepName]))
            ->body(__('filament-action-approvals::approval.notifications.requested_body', ApprovalNotificationContext::bodyParameters($approval, ApprovalNotificationEvent::Requested)))
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->warning();

        $notificationAction = ApprovalNotificationContext::resolveAction($approval, ApprovalNotificationEvent::Requested);

        if ($notificationAction) {
            $notification->actions([$notificationAction]);
        }

        $notification->sendToDatabase($recipient, config('filament-action-approvals.notifications.broadcast', false));
    }
}
