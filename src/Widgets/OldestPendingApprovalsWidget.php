<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Widgets;

use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Resources\Approvals\Schemas\ApprovalInfolist;
use CoringaWc\FilamentActionApprovals\Support\ApprovableModelLabel;
use CoringaWc\FilamentActionApprovals\Support\ApprovalDashboardFilters;
use CoringaWc\FilamentActionApprovals\Support\DateDisplay;
use CoringaWc\FilamentActionApprovals\Support\UserDisplayName;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OldestPendingApprovalsWidget extends TableWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 12;

    protected function getTableHeading(): ?string
    {
        return __('filament-action-approvals::approval.dashboard.widgets.oldest_pending');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('approvable_record')
                    ->label(__('filament-action-approvals::approval.approval_table.record'))
                    ->state(fn (Approval $record): string => ApprovableModelLabel::resolveWithKey($record->approvable_type, $record->approvable_id)),
                TextColumn::make('flow.name')
                    ->label(__('filament-action-approvals::approval.approval_table.flow')),
                TextColumn::make('submitted_by_display')
                    ->label(__('filament-action-approvals::approval.approval_table.submitted_by'))
                    ->state(fn (Approval $record): ?string => UserDisplayName::resolve($record->submitter)),
                TextColumn::make('current_step')
                    ->label(__('filament-action-approvals::approval.approval_table.current_step'))
                    ->state(function (Approval $record): string {
                        $stepInstance = $record->currentStepInstance();

                        if ($stepInstance === null || $stepInstance->step === null) {
                            return __('filament-action-approvals::approval.approval_table.no_current_step');
                        }

                        return $stepInstance->step->name;
                    }),
                DateDisplay::column(
                    TextColumn::make('submitted_at')
                        ->label(__('filament-action-approvals::approval.fields.submitted_at')),
                ),
            ])
            ->recordActions([
                ApprovalInfolist::configureViewAction(ViewAction::make()),
            ])
            ->paginated(false)
            ->defaultSort('submitted_at', 'asc');
    }

    /**
     * @return Builder<Approval>
     */
    protected function getTableQuery(): Builder
    {
        return ApprovalDashboardFilters::applyToQuery(
            Approval::query()
                ->withOperationalRelations()
                ->where('status', ApprovalStatus::Pending->value),
            $this->pageFilters,
            'submitted_at',
        )
            ->limit(10);
    }
}
