<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Widgets\ApprovalBottlenecksWidget;
use CoringaWc\FilamentActionApprovals\Widgets\ApprovalStatusChartWidget;
use CoringaWc\FilamentActionApprovals\Widgets\OldestPendingApprovalsWidget;
use Illuminate\Contracts\Support\Htmlable;

it('uses the translated heading for the approval status chart widget', function (): void {
    $widget = new ApprovalStatusChartWidget;

    expect($widget->getHeading())
        ->toBe(__('filament-action-approvals::approval.dashboard.widgets.status_chart'));
});

it('uses the translated heading for the bottlenecks widget', function (): void {
    $widget = new class extends ApprovalBottlenecksWidget
    {
        public function heading(): string|Htmlable|null
        {
            return $this->getTableHeading();
        }
    };

    expect($widget->heading())
        ->toBe(__('filament-action-approvals::approval.dashboard.widgets.bottlenecks'));
});

it('uses the translated heading for the oldest pending approvals widget', function (): void {
    $widget = new class extends OldestPendingApprovalsWidget
    {
        public function heading(): string|Htmlable|null
        {
            return $this->getTableHeading();
        }
    };

    expect($widget->heading())
        ->toBe(__('filament-action-approvals::approval.dashboard.widgets.oldest_pending'));
});
