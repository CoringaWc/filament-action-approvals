<?php

namespace CoringaWc\FilamentActionApprovals\Notifications;

use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\Approval;

class ApprovalApprovedNotification
{
    public static function send(Approval $approval, int|string $userId): void
    {
        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();
        $recipient = $userModel::find($userId);

        if (! $recipient) {
            return;
        }

        $approvable = $approval->approvable;
        $modelLabel = class_basename($approvable);

        Notification::make()
            ->title(__('filament-action-approvals::approval.notifications.approved_title'))
            ->body(__('filament-action-approvals::approval.notifications.approved_body', ['model' => $modelLabel, 'id' => $approvable->getKey()]))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->success()
            ->sendToDatabase($recipient);
    }
}
