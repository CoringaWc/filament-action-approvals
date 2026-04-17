<?php

namespace CoringaWc\FilamentActionApprovals\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\Approval;

class ApprovalsRelationManager extends RelationManager
{
    protected static string $relationship = 'approvals';

    public static function getTitle($ownerRecord = null, ?string $pageClass = null): string
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
                TextColumn::make('submitter.name')
                    ->label(__('filament-action-approvals::approval.relation_manager.submitted_by')),
                TextColumn::make('submitted_at')
                    ->label(__('filament-action-approvals::approval.fields.submitted_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('completed_at')
                    ->label(__('filament-action-approvals::approval.fields.completed_at'))
                    ->dateTime()
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
                                TextEntry::make('submitter.name')
                                    ->label(__('filament-action-approvals::approval.relation_manager.submitted_by')),
                                TextEntry::make('submitted_at')
                                    ->label(__('filament-action-approvals::approval.fields.submitted_at'))
                                    ->dateTime(),
                                TextEntry::make('completed_at')
                                    ->label(__('filament-action-approvals::approval.fields.completed_at'))
                                    ->dateTime()
                                    ->placeholder(__('filament-action-approvals::approval.relation_manager.in_progress')),
                            ])
                            ->columns(3),

                        Section::make(__('filament-action-approvals::approval.relation_manager.steps'))
                            ->schema([
                                RepeatableEntry::make('stepInstances')
                                    ->label('')
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
                                            ->state(function ($record): string {
                                                $ids = $record->assigned_approver_ids;

                                                if (empty($ids)) {
                                                    return '-';
                                                }

                                                $userModel = FilamentActionApprovalsPlugin::resolveUserModel();

                                                return $userModel::whereIn('id', $ids)
                                                    ->pluck('name')
                                                    ->join(', ') ?: '-';
                                            }),
                                        TextEntry::make('received_approvals')
                                            ->label(__('filament-action-approvals::approval.relation_manager.received_required'))
                                            ->formatStateUsing(fn ($record): string => "{$record->received_approvals} / {$record->required_approvals}"),
                                        TextEntry::make('sla_deadline')
                                            ->label(__('filament-action-approvals::approval.infolist.sla_deadline'))
                                            ->dateTime()
                                            ->placeholder(__('filament-action-approvals::approval.widgets.no_sla')),
                                    ])
                                    ->columns(3),
                            ]),

                        Section::make(__('filament-action-approvals::approval.relation_manager.audit_trail'))
                            ->schema([
                                RepeatableEntry::make('actions')
                                    ->label('')
                                    ->schema([
                                        TextEntry::make('type')
                                            ->label(__('filament-action-approvals::approval.fields.type'))
                                            ->badge(),
                                        TextEntry::make('user.name')
                                            ->label(__('filament-action-approvals::approval.relation_manager.by'))
                                            ->placeholder(__('filament-action-approvals::approval.relation_manager.system')),
                                        TextEntry::make('comment')
                                            ->label(__('filament-action-approvals::approval.fields.comment'))
                                            ->placeholder('-'),
                                        TextEntry::make('created_at')
                                            ->label(__('filament-action-approvals::approval.relation_manager.date'))
                                            ->dateTime(),
                                    ])
                                    ->columns(4),
                            ]),
                    ]))
                    ->slideOver()
                    ->modalHeading(fn (Approval $record): string => __('filament-action-approvals::approval.relation_manager.approval_heading', ['flow' => $record->flow->name]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament-action-approvals::approval.relation_manager.close')),
            ]);
    }
}
