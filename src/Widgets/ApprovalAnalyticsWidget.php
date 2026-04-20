<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Widgets;

use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Enums\StepInstanceStatus;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Support\ApprovalDashboardFilters;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ApprovalAnalyticsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 12;

    protected int|array|null $columns = [
        'xl' => 5,
    ];

    protected function getStats(): array
    {
        $averageApprovalHours = round(
            ApprovalDashboardFilters::applyToQuery(
                Approval::query()->whereNotNull('submitted_at')->whereNotNull('completed_at'),
                $this->pageFilters,
                'completed_at',
            )
                ->get(['submitted_at', 'completed_at'])
                ->avg(fn (Approval $approval): float => (float) $approval->completed_at?->diffInHours($approval->submitted_at))
                ?? 0,
            1,
        );

        return [
            Stat::make(
                __('filament-action-approvals::approval.widgets.pending_approvals'),
                ApprovalDashboardFilters::applyToQuery(Approval::query(), $this->pageFilters, 'submitted_at')
                    ->where('status', ApprovalStatus::Pending)
                    ->count(),
            )
                ->color('warning')
                ->icon(Heroicon::OutlinedClock),
            Stat::make(
                __('filament-action-approvals::approval.widgets.approved_30d'),
                ApprovalDashboardFilters::applyToQuery(Approval::query(), $this->pageFilters, 'completed_at')
                    ->where('status', ApprovalStatus::Approved)
                    ->count(),
            )
                ->color('success')
                ->icon(Heroicon::OutlinedCheckCircle),
            Stat::make(
                __('filament-action-approvals::approval.widgets.rejected_30d'),
                ApprovalDashboardFilters::applyToQuery(Approval::query(), $this->pageFilters, 'completed_at')
                    ->where('status', ApprovalStatus::Rejected)
                    ->count(),
            )
                ->color('danger')
                ->icon(Heroicon::OutlinedXCircle),
            Stat::make(
                __('filament-action-approvals::approval.widgets.overdue_steps'),
                ApprovalDashboardFilters::applyToQuery(ApprovalStepInstance::query(), $this->pageFilters, 'activated_at')
                    ->where('status', StepInstanceStatus::Waiting)
                    ->where('sla_deadline', '<', now())
                    ->count(),
            )
                ->color('danger')
                ->icon(Heroicon::OutlinedExclamationTriangle),
            Stat::make(
                __('filament-action-approvals::approval.dashboard.widgets.average_approval_time'),
                __('filament-action-approvals::approval.dashboard.widgets.average_hours_value', ['hours' => $averageApprovalHours]),
            )
                ->color('info')
                ->icon(Heroicon::OutlinedBolt),
        ];
    }
}
