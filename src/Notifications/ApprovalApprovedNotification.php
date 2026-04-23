<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Notifications;

use CoringaWc\FilamentActionApprovals\Enums\ApprovalNotificationEvent;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Support\ApprovalNotificationContext;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ApprovalApprovedNotification
{
    public static function send(Approval $approval, int|string $userId): void
    {
        if (! config('filament-action-approvals.notifications.database', true)) {
            return;
        }

        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();
        $recipient = $userModel::find($userId);

        if (! $recipient) {
            return;
        }

        $notification = Notification::make()
            ->title(__('filament-action-approvals::approval.notifications.approved_title'))
            ->body(__('filament-action-approvals::approval.notifications.approved_body', ApprovalNotificationContext::bodyParameters($approval, ApprovalNotificationEvent::Approved)))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->success();

        $notificationAction = ApprovalNotificationContext::resolveAction($approval, ApprovalNotificationEvent::Approved);

        if ($notificationAction) {
            $notification->actions([$notificationAction]);
        }

        $notification->sendToDatabase($recipient, config('filament-action-approvals.notifications.broadcast', false));
    }
}
