<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Pages;

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Support\ApprovalDashboardFilters;
use CoringaWc\FilamentActionApprovals\Widgets\ApprovalAnalyticsWidget;
use CoringaWc\FilamentActionApprovals\Widgets\ApprovalBottlenecksWidget;
use CoringaWc\FilamentActionApprovals\Widgets\ApprovalStatusChartWidget;
use CoringaWc\FilamentActionApprovals\Widgets\OldestPendingApprovalsWidget;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;

class ApprovalsDashboard extends BaseDashboard
{
    use HasFiltersAction;

    public function mount(): void
    {
        $this->filters ??= ['period' => '30d'];
    }

    public function persistsFiltersInSession(): bool
    {
        return false;
    }

    public static function getRoutePath(Panel $panel): string
    {
        return FilamentActionApprovalsPlugin::resolveDashboardRoutePath();
    }

    public static function getNavigationGroup(): ?string
    {
        return FilamentActionApprovalsPlugin::resolveNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-action-approvals::approval.dashboard.navigation_label');
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return FilamentActionApprovalsPlugin::resolveDashboardNavigationIcon()
            ?? Heroicon::OutlinedPresentationChartLine;
    }

    public static function getNavigationSort(): ?int
    {
        return FilamentActionApprovalsPlugin::resolveDashboardNavigationSort();
    }

    public function getTitle(): string
    {
        return __('filament-action-approvals::approval.dashboard.title');
    }

    public function getSubheading(): ?string
    {
        return __('filament-action-approvals::approval.dashboard.subheading', [
            'period' => ApprovalDashboardFilters::label($this->filters),
        ]);
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            ApprovalAnalyticsWidget::class,
            ApprovalStatusChartWidget::class,
            ApprovalBottlenecksWidget::class,
            OldestPendingApprovalsWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 12;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('last5Days')
                ->label(__('filament-action-approvals::approval.dashboard.filters.last_5_days_short'))
                ->color(fn (): string => $this->isSelectedPeriod('5d') ? 'primary' : 'gray')
                ->action(function (): void {
                    $this->applyPeriodFilter('5d');
                }),
            Action::make('last15Days')
                ->label(__('filament-action-approvals::approval.dashboard.filters.last_15_days_short'))
                ->color(fn (): string => $this->isSelectedPeriod('15d') ? 'primary' : 'gray')
                ->action(function (): void {
                    $this->applyPeriodFilter('15d');
                }),
            Action::make('last30Days')
                ->label(__('filament-action-approvals::approval.dashboard.filters.last_30_days_short'))
                ->color(fn (): string => $this->isSelectedPeriod('30d') ? 'primary' : 'gray')
                ->action(function (): void {
                    $this->applyPeriodFilter('30d');
                }),
            Action::make('allTime')
                ->label(__('filament-action-approvals::approval.dashboard.filters.all_time_short'))
                ->color(fn (): string => $this->isSelectedPeriod('all') ? 'primary' : 'gray')
                ->action(function (): void {
                    $this->applyPeriodFilter('all');
                }),
            FilterAction::make()
                ->hiddenLabel()
                ->tooltip(fn (Action $action) => $action->getLabel())
                ->schema([
                    DatePicker::make('startDate')
                        ->label(__('filament-action-approvals::approval.dashboard.filters.start_date')),
                    DatePicker::make('endDate')
                        ->label(__('filament-action-approvals::approval.dashboard.filters.end_date')),
                ]),
        ];
    }

    protected function isSelectedPeriod(string $period): bool
    {
        return ApprovalDashboardFilters::resolve($this->filters)['period'] === $period;
    }

    protected function applyPeriodFilter(string $period): void
    {
        $this->filters = [
            'period' => $period,
            'startDate' => null,
            'endDate' => null,
        ];
    }
}
