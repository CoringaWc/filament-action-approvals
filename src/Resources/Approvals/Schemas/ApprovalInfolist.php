<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Resources\Approvals\Schemas;

use CoringaWc\FilamentActionApprovals\Actions\ApproveAction;
use CoringaWc\FilamentActionApprovals\Actions\RejectAction;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalAction;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Support\ApprovableActionLabel;
use CoringaWc\FilamentActionApprovals\Support\ApprovableModelLabel;
use CoringaWc\FilamentActionApprovals\Support\DateDisplay;
use CoringaWc\FilamentActionApprovals\Support\UserDisplayName;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;

class ApprovalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make(__('filament-action-approvals::approval.infolist.current_step'))
                ->schema([

                    TextEntry::make('current_step_name')
                        ->label(__('filament-action-approvals::approval.infolist.step'))
                        ->state(fn (Approval $record): ?string => $record->currentStepInstance()?->step?->name)
                        ->placeholder(__('filament-action-approvals::approval.infolist.not_available')),
                    TextEntry::make('current_step_status')
                        ->label(__('filament-action-approvals::approval.infolist.status'))
                        ->state(fn (Approval $record) => $record->currentStepInstance()?->status)
                        ->badge()
                        ->placeholder(__('filament-action-approvals::approval.infolist.not_available')),
                    TextEntry::make('current_step_approvers')
                        ->label(__('filament-action-approvals::approval.infolist.pending_approvers'))
                        ->state(function (Approval $record): string {
                            $ids = $record->currentStepInstance()?->assigned_approver_ids;

                            if (empty($ids)) {
                                return __('filament-action-approvals::approval.infolist.not_available');
                            }

                            return UserDisplayName::resolveMany(
                                array_map('intval', $ids),
                                __('filament-action-approvals::approval.infolist.not_available'),
                            );
                        }),
                    TextEntry::make('current_step_progress')
                        ->label(__('filament-action-approvals::approval.infolist.progress'))
                        ->state(function (Approval $record): string {
                            $stepInstance = $record->currentStepInstance();

                            if (! $stepInstance instanceof ApprovalStepInstance) {
                                return __('filament-action-approvals::approval.infolist.not_available');
                            }

                            return __('filament-action-approvals::approval.infolist.approvals_count', [
                                'received' => $stepInstance->received_approvals,
                                'required' => $stepInstance->required_approvals,
                            ]);
                        }),
                    DateDisplay::entry(
                        TextEntry::make('current_step_sla')
                            ->label(__('filament-action-approvals::approval.infolist.sla_deadline'))
                            ->state(fn (Approval $record) => $record->currentStepInstance()?->sla_deadline),
                    )
                        ->placeholder(__('filament-action-approvals::approval.infolist.no_sla')),

                    Actions::make([
                        ApproveAction::make(),
                        RejectAction::make(),
                    ])
                        ->key('currentStepApprovalActions')
                        ->columnSpanFull()
                        ->alignment(Alignment::End)
                        ->visible(function (Approval $record): bool {
                            $userId = auth()->id();

                            if ($userId === null) {
                                return false;
                            }

                            return (FilamentActionApprovalsPlugin::isOperationalActionEnabled('approve') && $record->canBeApprovedBy($userId))
                                || (FilamentActionApprovalsPlugin::isOperationalActionEnabled('reject') && $record->canBeRejectedBy($userId));
                        }),
                ])
                ->collapsible()
                ->columns(3)
                ->visible(fn (Approval $record): bool => $record->currentStepInstance() !== null),

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
                                ->state(fn (ApprovalStepInstance $record): string => UserDisplayName::resolveMany(
                                    array_map('intval', $record->assigned_approver_ids),
                                    __('filament-action-approvals::approval.relation_manager.not_available'),
                                )),
                            TextEntry::make('received_approvals')
                                ->label(__('filament-action-approvals::approval.relation_manager.received_required'))
                                ->formatStateUsing(fn (ApprovalStepInstance $record): string => "{$record->received_approvals} / {$record->required_approvals}"),
                            DateDisplay::entry(
                                TextEntry::make('sla_deadline')
                                    ->label(__('filament-action-approvals::approval.infolist.sla_deadline')),
                            )
                                ->placeholder(__('filament-action-approvals::approval.widgets.no_sla')),
                        ])
                        ->columns(3),
                ])
                ->collapsible(),

            Section::make(__('filament-action-approvals::approval.infolist.approval_details'))
                ->schema([
                    TextEntry::make('status')
                        ->label(__('filament-action-approvals::approval.fields.status'))
                        ->badge(),
                    TextEntry::make('approvable_record')
                        ->label(__('filament-action-approvals::approval.infolist.record'))
                        ->state(fn (Approval $record): string => ApprovableModelLabel::resolveWithKey(
                            $record->approvable_type,
                            $record->approvable_id,
                        )),
                    TextEntry::make('flow.name')
                        ->label(__('filament-action-approvals::approval.infolist.flow')),
                    TextEntry::make('action_key')
                        ->label(__('filament-action-approvals::approval.infolist.action'))
                        ->state(fn (Approval $record): string => ApprovableActionLabel::resolve(
                            $record->approvable_type,
                            $record->submittedActionKey(),
                        )),
                    TextEntry::make('submitted_by_display')
                        ->label(__('filament-action-approvals::approval.infolist.submitted_by'))
                        ->state(fn (Approval $record): ?string => UserDisplayName::resolve($record->submitter))
                        ->placeholder(__('filament-action-approvals::approval.infolist.not_available')),
                    DateDisplay::entry(
                        TextEntry::make('submitted_at')
                            ->label(__('filament-action-approvals::approval.fields.submitted_at')),
                    ),
                    DateDisplay::entry(
                        TextEntry::make('completed_at')
                            ->label(__('filament-action-approvals::approval.fields.completed_at')),
                    )
                        ->placeholder(__('filament-action-approvals::approval.relation_manager.in_progress')),
                    TextEntry::make('rejection_reason')
                        ->label(__('filament-action-approvals::approval.infolist.rejection_reason'))
                        ->state(fn (Approval $record): ?string => $record->latestRejectionReason())
                        ->placeholder(__('filament-action-approvals::approval.infolist.not_available'))
                        ->visible(fn (Approval $record): bool => $record->status === ApprovalStatus::Rejected
                            && filled($record->latestRejectionReason()))
                        ->columnSpanFull(),
                ])
                ->columns(3)
                ->collapsible(),

            Section::make(__('filament-action-approvals::approval.relation_manager.audit_trail'))
                ->schema([
                    RepeatableEntry::make('actions')
                        ->hiddenLabel()
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
                ])
                ->collapsible(),
        ]);
    }

    public static function configureViewAction(ViewAction $action): ViewAction
    {
        return $action
            ->slideOver()
            ->infolist(fn (Schema $schema): Schema => static::configure($schema))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('filament-action-approvals::approval.relation_manager.close'))
            ->modalHeading(function (Approval $record): string {
                $actionLabel = ApprovableActionLabel::resolve(
                    $record->approvable_type,
                    $record->submittedActionKey(),
                );

                return __('filament-action-approvals::approval.relation_manager.approval_heading', [
                    'flow' => $actionLabel
                        ?? __('filament-action-approvals::approval.relation_manager.not_available'),
                ]);
            });
    }
}
