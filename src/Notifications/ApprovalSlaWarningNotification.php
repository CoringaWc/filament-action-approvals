<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Notifications;

use CoringaWc\FilamentActionApprovals\Enums\ApprovalNotificationEvent;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Support\ApprovalNotificationContext;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ApprovalSlaWarningNotification
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
        $deadline = $stepInstance->sla_deadline?->diffForHumans();

        if ($deadline === null) {
            return;
        }

        $notification = Notification::make()
            ->title(__('filament-action-approvals::approval.notifications.sla_warning_title'))
            ->body(__('filament-action-approvals::approval.notifications.sla_warning_body', [
                ...ApprovalNotificationContext::bodyParameters($approval, ApprovalNotificationEvent::SlaWarning),
                'deadline' => $deadline,
            ]))
            ->icon(Heroicon::OutlinedClock)
            ->warning();

        $notificationAction = ApprovalNotificationContext::resolveAction($approval, ApprovalNotificationEvent::SlaWarning);

        if ($notificationAction) {
            $notification->actions([$notificationAction]);
        }

        $notification->sendToDatabase($recipient, config('filament-action-approvals.notifications.broadcast', false));
    }
}
