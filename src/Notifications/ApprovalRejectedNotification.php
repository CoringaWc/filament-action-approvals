<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Notifications;

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Support\ApprovableModelLabel;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ApprovalRejectedNotification
{
    public static function send(Approval $approval, int $userId): void
    {
        if (! config('filament-action-approvals.notifications.database', true)) {
            return;
        }

        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();
        $recipient = $userModel::find($userId);

        if (! $recipient) {
            return;
        }

        $approvable = $approval->approvable;
        $modelLabel = ApprovableModelLabel::resolve($approvable);
        $approvableKey = $approvable?->getKey() ?? __('filament-action-approvals::approval.relation_manager.not_available');

        Notification::make()
            ->title(__('filament-action-approvals::approval.notifications.rejected_title'))
            ->body(__('filament-action-approvals::approval.notifications.rejected_body', ['model' => $modelLabel, 'id' => $approvableKey]))
            ->icon(Heroicon::OutlinedXCircle)
            ->danger()
            ->sendToDatabase($recipient, config('filament-action-approvals.notifications.broadcast', false));
    }
}
