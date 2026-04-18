<?php

namespace CoringaWc\FilamentActionApprovals\Notifications;

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Support\ApprovableModelLabel;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ApprovalSlaWarningNotification
{
    public static function send(ApprovalStepInstance $stepInstance, int $userId): void
    {
        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();
        $recipient = $userModel::find($userId);

        if (! $recipient) {
            return;
        }

        $approvable = $stepInstance->approval->approvable;
        $modelLabel = ApprovableModelLabel::resolve($approvable);
        $approvableKey = $approvable?->getKey() ?? __('filament-action-approvals::approval.relation_manager.not_available');
        $deadline = $stepInstance->sla_deadline?->diffForHumans();

        if ($deadline === null) {
            return;
        }

        Notification::make()
            ->title(__('filament-action-approvals::approval.notifications.sla_warning_title'))
            ->body(__('filament-action-approvals::approval.notifications.sla_warning_body', ['model' => $modelLabel, 'id' => $approvableKey, 'deadline' => $deadline]))
            ->icon(Heroicon::OutlinedClock)
            ->warning()
            ->sendToDatabase($recipient);
    }
}
