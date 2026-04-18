<?php

namespace CoringaWc\FilamentActionApprovals\RelationManagers;

use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalAction;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Support\DateDisplay;
use CoringaWc\FilamentActionApprovals\Support\UserDisplayName;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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
                ViewAction::make()
                    ->infolist(fn (Schema $schema): Schema => $schema->components([
                        Section::make(__('filament-action-approvals::approval.relation_manager.approval_details'))
                            ->schema([
                                TextEntry::make('flow.name')
                                    ->label(__('filament-action-approvals::approval.relation_manager.flow')),
                                TextEntry::make('status')
                                    ->label(__('filament-action-approvals::approval.fields.status'))
                                    ->badge(),
                                TextEntry::make('submitted_by_display')
                                    ->label(__('filament-action-approvals::approval.relation_manager.submitted_by'))
                                    ->state(fn (Approval $record): ?string => UserDisplayName::resolve($record->submitter)),
                                DateDisplay::entry(
                                    TextEntry::make('submitted_at')
                                        ->label(__('filament-action-approvals::approval.fields.submitted_at')),
                                ),
                                DateDisplay::entry(
                                    TextEntry::make('completed_at')
                                        ->label(__('filament-action-approvals::approval.fields.completed_at')),
                                )
                                    ->placeholder(__('filament-action-approvals::approval.relation_manager.in_progress')),
                            ])
                            ->columns(3),

                        Section::make(__('filament-action-approvals::approval.relation_manager.steps'))
                            ->schema([
                                RepeatableEntry::make('stepInstances')
                                    ->hiddenLabel()
                                    ->schema([
                                        TextEntry::make('step.name')
                                            ->label(__('filament-action-approvals::approval.widgets.step')),
                                        TextEntry::make('type')
                                            ->label(__('filament-action-approvals::approval.flow.type'))
                                            ->badge(),
                                        TextEntry::make('status')
                                            ->label(__('filament-action-approvals::approval.fields.status'))
                                            ->badge(),
                                        TextEntry::make('approvers_display')
                                            ->label(__('filament-action-approvals::approval.relation_manager.approvers'))
                                            ->state(fn ($record): string => UserDisplayName::resolveMany(
                                                $record->assigned_approver_ids,
                                                __('filament-action-approvals::approval.relation_manager.not_available'),
                                            )),
                                        TextEntry::make('received_approvals')
                                            ->label(__('filament-action-approvals::approval.relation_manager.received_required'))
                                            ->formatStateUsing(fn ($record): string => "{$record->received_approvals} / {$record->required_approvals}"),
                                        DateDisplay::entry(
                                            TextEntry::make('sla_deadline')
                                                ->label(__('filament-action-approvals::approval.infolist.sla_deadline')),
                                        )
                                            ->placeholder(__('filament-action-approvals::approval.widgets.no_sla')),
                                    ])
                                    ->columns(3),
                            ]),

                        Section::make(__('filament-action-approvals::approval.relation_manager.audit_trail'))
                            ->schema([
                                RepeatableEntry::make('auditTrail')
                                    ->hiddenLabel()
                                    ->state(fn (Approval $record) => $record->actions()->with('user')->get())
                                    ->schema([
                                        TextEntry::make('type')
                                            ->label(__('filament-action-approvals::approval.fields.type'))
                                            ->badge(),
                                        TextEntry::make('actor_name')
                                            ->label(__('filament-action-approvals::approval.relation_manager.by'))
                                            ->state(fn (ApprovalAction $record): ?string => UserDisplayName::resolve($record->user))
                                            ->placeholder(__('filament-action-approvals::approval.relation_manager.system')),
                                        TextEntry::make('comment')
                                            ->label(__('filament-action-approvals::approval.fields.comment'))
                                            ->placeholder(__('filament-action-approvals::approval.relation_manager.not_available')),
                                        DateDisplay::entry(
                                            TextEntry::make('created_at')
                                                ->label(__('filament-action-approvals::approval.relation_manager.date')),
                                        ),
                                    ])
                                    ->columns(4),
                            ]),
                    ]))
                    ->slideOver()
                    ->modalHeading(function (Approval $record): string {
                        $flow = $record->getRelationValue('flow');

                        return __('filament-action-approvals::approval.relation_manager.approval_heading', [
                            'flow' => $flow instanceof ApprovalFlow
                                ? $flow->name
                                : __('filament-action-approvals::approval.relation_manager.not_available'),
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament-action-approvals::approval.relation_manager.close')),
            ]);
    }
}
