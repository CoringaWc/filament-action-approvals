<?php

namespace CoringaWc\FilamentActionApprovals\Notifications;

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Support\ApprovableModelLabel;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ApprovalRequestedNotification
{
    public static function send(ApprovalStepInstance $stepInstance, int|string $userId): void
    {
        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();
        $recipient = $userModel::find($userId);

        if (! $recipient) {
            return;
        }

        $approvable = $stepInstance->approval->approvable;
        $modelLabel = ApprovableModelLabel::resolve($approvable);

        Notification::make()
            ->title(__('filament-action-approvals::approval.notifications.requested_title', ['step' => $stepInstance->step?->name ?? 'Unknown']))
            ->body(__('filament-action-approvals::approval.notifications.requested_body', ['model' => $modelLabel, 'id' => $approvable->getKey()]))
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->warning()
            ->sendToDatabase($recipient);
    }
}
