<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Widgets;

use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Support\ApprovalDashboardFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class ApprovalStatusChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 6;

    protected function getType(): string
    {
        return 'doughnut';
    }

    public function getHeading(): ?string
    {
        return __('filament-action-approvals::approval.dashboard.widgets.status_chart');
    }

    public function getDescription(): ?string
    {
        return ApprovalDashboardFilters::label($this->pageFilters);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $statuses = [
            ApprovalStatus::Pending,
            ApprovalStatus::Approved,
            ApprovalStatus::Rejected,
            ApprovalStatus::Cancelled,
        ];

        $counts = array_map(function (ApprovalStatus $status): int {
            return ApprovalDashboardFilters::applyToQuery(Approval::query(), $this->pageFilters, 'submitted_at')
                ->where('status', $status->value)
                ->count();
        }, $statuses);

        return [
            'datasets' => [[
                'label' => __('filament-action-approvals::approval.approvals'),
                'data' => $counts,
                'backgroundColor' => ['#f59e0b', '#10b981', '#ef4444', '#6b7280'],
            ]],
            'labels' => array_map(
                fn (ApprovalStatus $status): string => (string) $status->getLabel(),
                $statuses,
            ),
        ];
    }
}
