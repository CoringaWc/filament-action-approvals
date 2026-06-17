<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Schemas\Components;

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Support\CurrentPanelUser;
use Filament\Schemas\Components\Callout;

final class ApprovalRequestCallout
{
    public static function make(?string $heading = null, ?string $description = null): Callout
    {
        return Callout::make($heading ?? __('filament-action-approvals::approval.modal.approval_request_callout.heading'))
            ->description($description ?? __('filament-action-approvals::approval.modal.approval_request_callout.description'))
            ->warning()
            ->hidden(fn (): bool => FilamentActionApprovalsPlugin::canApplyDirectly(CurrentPanelUser::id()));
    }
}
