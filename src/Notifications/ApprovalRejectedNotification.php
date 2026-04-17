<?php

namespace CoringaWc\FilamentActionApprovals\Notifications;

use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\Approval;

class ApprovalRejectedNotification
{
    public static function send(Approval $approval, int $userId): void
    {
        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();
        $recipient = $userModel::find($userId);

        if (! $recipient) {
            return;
        }

        $approvable = $approval->approvable;
        $modelLabel = class_basename($approvable);

        Notification::make()
            ->title(__('filament-action-approvals::approval.notifications.rejected_title'))
            ->body(__('filament-action-approvals::approval.notifications.rejected_body', ['model' => $modelLabel, 'id' => $approvable->getKey()]))
            ->icon(Heroicon::OutlinedXCircle)
            ->danger()
            ->sendToDatabase($recipient);
    }
}
