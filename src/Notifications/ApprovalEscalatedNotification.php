<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Notifications;

use CoringaWc\FilamentActionApprovals\Enums\ApprovalNotificationEvent;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Support\ApprovalNotificationContext;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ApprovalEscalatedNotification
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
        $notification = Notification::make()
            ->title(__('filament-action-approvals::approval.notifications.escalated_title'))
            ->body(__('filament-action-approvals::approval.notifications.escalated_body', ApprovalNotificationContext::bodyParameters($approval, ApprovalNotificationEvent::Escalated)))
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->danger();

        $notificationAction = ApprovalNotificationContext::resolveAction($approval, ApprovalNotificationEvent::Escalated);

        if ($notificationAction) {
            $notification->actions([$notificationAction]);
        }

        $notification->sendToDatabase($recipient, config('filament-action-approvals.notifications.broadcast', false));
    }
}
