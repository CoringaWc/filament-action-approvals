<?php

namespace CoringaWc\FilamentActionApprovals\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Enums\StepInstanceStatus;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;

class ApprovalAnalyticsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        return [
            Stat::make(__('filament-action-approvals::approval.widgets.pending_approvals'), Approval::where('status', ApprovalStatus::Pending)->count())
                ->color('warning')
                ->icon(Heroicon::OutlinedClock),
            Stat::make(__('filament-action-approvals::approval.widgets.approved_30d'), Approval::where('status', ApprovalStatus::Approved)
                ->where('completed_at', '>=', now()->subDays(30))->count())
                ->color('success')
                ->icon(Heroicon::OutlinedCheckCircle),
            Stat::make(__('filament-action-approvals::approval.widgets.rejected_30d'), Approval::where('status', ApprovalStatus::Rejected)
                ->where('completed_at', '>=', now()->subDays(30))->count())
                ->color('danger')
                ->icon(Heroicon::OutlinedXCircle),
            Stat::make(__('filament-action-approvals::approval.widgets.overdue_steps'), ApprovalStepInstance::where('status', StepInstanceStatus::Waiting)
                ->where('sla_deadline', '<', now())->count())
                ->color('danger')
                ->icon(Heroicon::OutlinedExclamationTriangle),
        ];
    }
}
