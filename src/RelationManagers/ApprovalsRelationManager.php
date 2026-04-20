<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\RelationManagers;

use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Resources\Approvals\Schemas\ApprovalInfolist;
use CoringaWc\FilamentActionApprovals\Support\DateDisplay;
use CoringaWc\FilamentActionApprovals\Support\UserDisplayName;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

class ApprovalsRelationManager extends RelationManager
{
    protected static string $relationship = 'approvals';

    #[On('filament-action-approvals::approval-updated')]
    public function refreshApprovals(): void
    {
        $this->resetTable();
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament-action-approvals::approval.relation_manager.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('flow.name')
                    ->label(__('filament-action-approvals::approval.relation_manager.flow')),
                TextColumn::make('status')
                    ->label(__('filament-action-approvals::approval.fields.status'))
                    ->badge(),
                TextColumn::make('submitted_by_name')
                    ->label(__('filament-action-approvals::approval.relation_manager.submitted_by'))
                    ->state(fn (Approval $record): ?string => UserDisplayName::resolve($record->submitter)),
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
            ->defaultSort('created_at', 'desc')
            ->actions([
                ApprovalInfolist::configureViewAction(ViewAction::make()),
            ]);
    }
}
