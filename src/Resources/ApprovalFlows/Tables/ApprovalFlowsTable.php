<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Resources\ApprovalFlows\Tables;

use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Support\ApprovableActionLabel;
use CoringaWc\FilamentActionApprovals\Support\ApprovableModelLabel;
use CoringaWc\FilamentActionApprovals\Support\DateDisplay;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApprovalFlowsTable
{
    /**
     * @return array<int, EditAction|DeleteAction>
     */
    protected static function recordActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @return array<int, TextColumn|IconColumn>
     */
    protected static function columns(): array
    {
        return [
            TextColumn::make('name')
                ->label(__('filament-action-approvals::approval.flow_table.name'))
                ->searchable()
                ->sortable(),
            TextColumn::make('approvable_type')
                ->label(__('filament-action-approvals::approval.flow_table.model'))
                ->placeholder(__('filament-action-approvals::approval.flow_table.any'))
                ->formatStateUsing(fn (?string $state): string => ApprovableModelLabel::resolve($state)),
            TextColumn::make('action_key')
                ->label(__('filament-action-approvals::approval.flow_table.action_key'))
                ->placeholder(__('filament-action-approvals::approval.flow.any_action'))
                ->formatStateUsing(fn (ApprovalFlow $record): string => ApprovableActionLabel::resolve($record->approvable_type, $record->action_key))
                ->searchable(),
            TextColumn::make('steps_count')
                ->counts('steps')
                ->label(__('filament-action-approvals::approval.flow_table.steps')),
            IconColumn::make('is_active')
                ->label(__('filament-action-approvals::approval.flow_table.is_active'))
                ->boolean(),
            DateDisplay::column(
                TextColumn::make('created_at')
                    ->label(__('filament-action-approvals::approval.flow_table.created_at')),
            )
                ->sortable(),
        ];
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns(static::columns())
            ->recordActions(static::recordActions());
    }
}
