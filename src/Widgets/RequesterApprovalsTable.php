<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Widgets;

use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Resources\Approvals\Tables\ApprovalsTable;
use CoringaWc\FilamentActionApprovals\Support\ApprovableActionLabel;
use CoringaWc\FilamentActionApprovals\Support\ApprovalModels;
use CoringaWc\FilamentActionApprovals\Support\CurrentPanelUser;
use CoringaWc\FilamentActionApprovals\Support\DateDisplay;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RequesterApprovalsTable extends TableWidget
{
    protected static bool $isLazy = false;

    public ?string $approvableType = null;

    public ?string $approvableId = null;

    /**
     * @var array<string, mixed>
     */
    public array $context = [];

    protected int|string|array $columnSpan = 'full';

    /**
     * @param  array<string, mixed>  $context
     */
    public function mount(?string $approvableType = null, ?string $approvableId = null, array $context = []): void
    {
        $this->approvableType = filled($approvableType) ? $approvableType : null;
        $this->approvableId = filled($approvableId) ? $approvableId : null;
        $this->context = $context;
    }

    protected function getTableHeading(): string
    {
        return '';
    }

    public function table(Table $table): Table
    {
        return ApprovalsTable::configureContextual($table)
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('status')
                    ->label(__('filament-action-approvals::approval.fields.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('action_key')
                    ->label(__('filament-action-approvals::approval.approval_table.action'))
                    ->state(fn (Approval $record): string => ApprovableActionLabel::resolve(
                        $record->approvable_type,
                        $record->submittedActionKey(),
                    )),
                DateDisplay::column(
                    TextColumn::make('submitted_at')
                        ->label(__('filament-action-approvals::approval.fields.submitted_at')),
                )
                    ->sortable(),
                DateDisplay::column(
                    TextColumn::make('completed_at')
                        ->label(__('filament-action-approvals::approval.fields.completed_at')),
                )
                    ->placeholder(__('filament-action-approvals::approval.relation_manager.in_progress'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('filament-action-approvals::approval.fields.status'))
                    ->options(ApprovalStatus::class),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->paginated([10, 25]);
    }

    /**
     * @param  Builder<Approval>  $query
     * @param  array<string, mixed>  $parameters
     * @return Builder<Approval>
     */
    public static function scopeToContext(Builder $query, array $parameters): Builder
    {
        $approvableType = $parameters['approvableType'] ?? null;
        $approvableId = $parameters['approvableId'] ?? null;

        $query = $query
            ->when(
                is_string($approvableType) && filled($approvableType),
                fn (Builder $builder): Builder => $builder->where('approvable_type', $approvableType),
            )
            ->when(
                ! is_array($approvableId) && filled($approvableId),
                fn (Builder $builder): Builder => $builder->where('approvable_id', (string) $approvableId),
            );

        return FilamentActionApprovalsPlugin::scopeRequesterApprovalsForCurrentPanel($query, $parameters);
    }

    /**
     * @return Builder<Approval>
     */
    protected function getTableQuery(): Builder
    {
        return static::scopeToContext(
            ApprovalModels::approvalQuery()
                ->submittedByRequester(CurrentPanelUser::model())
                ->withOperationalRelations(),
            [
                'approvableType' => (string) $this->approvableType,
                'approvableId' => (string) $this->approvableId,
                'context' => $this->context,
            ],
        );
    }
}
