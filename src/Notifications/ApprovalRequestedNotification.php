<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Notifications;

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Support\ApprovableModelLabel;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ApprovalRequestedNotification
{
    public static function send(ApprovalStepInstance $stepInstance, int $userId): void
    {
        if (! config('filament-action-approvals.notifications.database', true)) {
            return;
        }

        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();
        $recipient = $userModel::find($userId);

        if (! $recipient) {
            return;
        }

        $approvable = $stepInstance->approval->approvable;
        $step = $stepInstance->step;
        $modelLabel = ApprovableModelLabel::resolve($approvable);
        $approvableKey = $approvable?->getKey() ?? __('filament-action-approvals::approval.relation_manager.not_available');
        $stepName = $step ? $step->name : __('filament-action-approvals::approval.relation_manager.not_available');

        Notification::make()
            ->title(__('filament-action-approvals::approval.notifications.requested_title', ['step' => $stepName]))
            ->body(__('filament-action-approvals::approval.notifications.requested_body', ['model' => $modelLabel, 'id' => $approvableKey]))
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->warning()
            ->sendToDatabase($recipient, config('filament-action-approvals.notifications.broadcast', false));
    }
}
