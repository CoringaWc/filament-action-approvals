<?php

namespace CoringaWc\FilamentActionApprovals\Notifications;

use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;

class ApprovalEscalatedNotification
{
    public static function send(ApprovalStepInstance $stepInstance, int|string $userId): void
    {
        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();
        $recipient = $userModel::find($userId);

        if (! $recipient) {
            return;
        }

        $approvable = $stepInstance->approval->approvable;
        $modelLabel = class_basename($approvable);

        Notification::make()
            ->title(__('filament-action-approvals::approval.notifications.escalated_title'))
            ->body(__('filament-action-approvals::approval.notifications.escalated_body', ['model' => $modelLabel, 'id' => $approvable->getKey()]))
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->danger()
            ->sendToDatabase($recipient);
    }
}
