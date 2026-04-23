<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use CoringaWc\FilamentActionApprovals\Enums\ApprovalNotificationEvent;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

class ApprovalNotificationContext
{
    /**
     * @return array{model: string, id: string|int, record: string|int}
     */
    public static function bodyParameters(Approval $approval, ApprovalNotificationEvent $event): array
    {
        $recordLabel = static::resolveRecordLabel($approval, $event);

        return [
            'model' => ApprovableModelLabel::resolve($approval->approvable),
            'id' => $recordLabel,
            'record' => $recordLabel,
        ];
    }

    public static function resolveRecordLabel(Approval $approval, ApprovalNotificationEvent $event): string|int
    {
        $approvable = $approval->approvable;
        $fallback = $approvable?->getKey() ?? __('filament-action-approvals::approval.relation_manager.not_available');

        if (! $approvable instanceof Model || ! method_exists($approvable, 'getApprovalNotificationRecordLabel')) {
            return $fallback;
        }

        $recordLabel = $approvable->getApprovalNotificationRecordLabel($approval, $event);

        if (blank($recordLabel)) {
            return $fallback;
        }

        return $recordLabel;
    }

    public static function resolveAction(Approval $approval, ApprovalNotificationEvent $event): ?Action
    {
        $approvable = $approval->approvable;

        if (! $approvable instanceof Model || ! method_exists($approvable, 'getApprovalNotificationAction')) {
            return null;
        }

        $notificationAction = $approvable->getApprovalNotificationAction($approval, $event);

        if (! $notificationAction instanceof ApprovalNotificationAction || blank($notificationAction->url)) {
            return null;
        }

        return Action::make('viewRecord')
            ->label($notificationAction->label ?? __('filament-action-approvals::approval.notifications.view_record'))
            ->url($notificationAction->url, shouldOpenInNewTab: $notificationAction->shouldOpenInNewTab);
    }
}
