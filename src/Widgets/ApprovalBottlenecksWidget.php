<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Widgets;

use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Enums\StepInstanceStatus;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Support\ApprovableActionLabel;
use CoringaWc\FilamentActionApprovals\Support\ApprovableModelLabel;
use CoringaWc\FilamentActionApprovals\Support\ApprovalDashboardFilters;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ApprovalBottlenecksWidget extends TableWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 6;

    protected function getTableHeading(): ?string
    {
        return __('filament-action-approvals::approval.dashboard.widgets.bottlenecks');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-action-approvals::approval.flow_table.name')),
                TextColumn::make('approvable_type')
                    ->label(__('filament-action-approvals::approval.flow_table.model'))
                    ->state(fn (ApprovalFlow $record): string => ApprovableModelLabel::resolve($record->approvable_type)),
                TextColumn::make('action_key')
                    ->label(__('filament-action-approvals::approval.flow_table.action_key'))
                    ->state(fn (ApprovalFlow $record): string => ApprovableActionLabel::resolve($record->approvable_type, $record->action_key)),
                TextColumn::make('pending_approvals_count')
                    ->label(__('filament-action-approvals::approval.dashboard.widgets.pending_count'))
                    ->badge()
                    ->color('warning'),
                TextColumn::make('overdue_approvals_count')
                    ->label(__('filament-action-approvals::approval.dashboard.widgets.overdue_count'))
                    ->badge()
                    ->color('danger'),
            ])
            ->paginated(false)
            ->defaultSort('overdue_approvals_count', 'desc');
    }

    /**
     * @return Builder<ApprovalFlow>
     */
    protected function getTableQuery(): Builder
    {
        return ApprovalFlow::query()
            ->withCount([
                'approvals as pending_approvals_count' => function (Builder $query): void {
                    ApprovalDashboardFilters::applyToQuery(
                        $query->where('status', ApprovalStatus::Pending->value),
                        $this->pageFilters,
                        'submitted_at',
                    );
                },
                'approvals as overdue_approvals_count' => function (Builder $query): void {
                    ApprovalDashboardFilters::applyToQuery(
                        $query
                            ->where('status', ApprovalStatus::Pending->value)
                            ->whereHas(
                                'stepInstances',
                                fn (Builder $steps): Builder => $steps
                                    ->where('status', StepInstanceStatus::Waiting->value)
                                    ->whereNotNull('sla_deadline')
                                    ->where('sla_deadline', '<', now()),
                            ),
                        $this->pageFilters,
                        'submitted_at',
                    );
                },
            ])
            ->whereHas('approvals', function (Builder $query): void {
                ApprovalDashboardFilters::applyToQuery(
                    $query->where('status', ApprovalStatus::Pending->value),
                    $this->pageFilters,
                    'submitted_at',
                );
            })
            ->orderByDesc('overdue_approvals_count')
            ->orderByDesc('pending_approvals_count')
            ->limit(5);
    }
}
