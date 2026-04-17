<?php

namespace CoringaWc\FilamentActionApprovals\Notifications;

use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;

class ApprovalSlaWarningNotification
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
        $deadline = $stepInstance->sla_deadline->diffForHumans();

        Notification::make()
            ->title(__('filament-action-approvals::approval.notifications.sla_warning_title'))
            ->body(__('filament-action-approvals::approval.notifications.sla_warning_body', ['model' => $modelLabel, 'id' => $approvable->getKey(), 'deadline' => $deadline]))
            ->icon(Heroicon::OutlinedClock)
            ->warning()
            ->sendToDatabase($recipient);
    }
}
