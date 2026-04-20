<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Infolists;

use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalAction;
use CoringaWc\FilamentActionApprovals\Support\ApprovableActionLabel;
use CoringaWc\FilamentActionApprovals\Support\DateDisplay;
use CoringaWc\FilamentActionApprovals\Support\UserDisplayName;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;

class ApprovalStatusSection
{
    public static function make(): Section
    {
        return Section::make(__('filament-action-approvals::approval.infolist.approval_status'))
            ->schema([
                TextEntry::make('latestApprovalStatus')
                    ->label(__('filament-action-approvals::approval.infolist.status'))
                    ->state(fn ($record) => $record->latestApproval()?->status)
                    ->badge()
                    ->placeholder(__('filament-action-approvals::approval.infolist.not_submitted'))
                    ->columnSpan(1),
                TextEntry::make('latestApprovalFlowName')
                    ->label(__('filament-action-approvals::approval.infolist.flow'))
                    ->state(fn ($record) => $record->latestApproval()?->flow?->name)
                    ->placeholder(__('filament-action-approvals::approval.infolist.not_available'))
                    ->columnSpan(1),
                TextEntry::make('latestApprovalAction')
                    ->label(__('filament-action-approvals::approval.infolist.action'))
                    ->state(fn ($record): string => ApprovableActionLabel::resolve(
                        $record->getMorphClass(),
                        $record->latestApproval()?->submittedActionKey(),
                    ))
                    ->placeholder(__('filament-action-approvals::approval.infolist.not_available'))
                    ->columnSpan(1),
                TextEntry::make('latestApprovalSubmittedBy')
                    ->label(__('filament-action-approvals::approval.infolist.submitted_by'))
                    ->state(fn ($record) => UserDisplayName::resolve($record->latestApproval()?->submitter))
                    ->placeholder(__('filament-action-approvals::approval.infolist.not_available'))
                    ->columnSpan(1),
                DateDisplay::entry(
                    TextEntry::make('latestApprovalSubmittedAt')
                        ->label(__('filament-action-approvals::approval.infolist.submitted'))
                        ->state(fn ($record) => $record->latestApproval()?->submitted_at),
                )
                    ->placeholder(__('filament-action-approvals::approval.infolist.not_submitted'))
                    ->columnSpan(1),
                DateDisplay::entry(
                    TextEntry::make('latestApprovalCompletedAt')
                        ->label(__('filament-action-approvals::approval.infolist.completed'))
                        ->state(fn ($record) => $record->latestApproval()?->completed_at),
                )
                    ->placeholder(__('filament-action-approvals::approval.infolist.in_progress'))
                    ->columnSpan(1),

                TextEntry::make('rejectionReason')
                    ->label(__('filament-action-approvals::approval.infolist.rejection_reason'))
                    ->state(fn ($record) => $record->latestApproval()?->latestRejectionReason())
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->columnSpanFull()
                    ->visible(function ($record): bool {
                        $approval = $record->latestApproval();

                        return $approval instanceof Approval
                            && $approval->status === ApprovalStatus::Rejected
                            && $approval->latestRejectionReason() !== null;
                    }),

                Section::make(__('filament-action-approvals::approval.infolist.current_step'))
                    ->schema([
                        TextEntry::make('currentStepName')
                            ->label(__('filament-action-approvals::approval.infolist.step'))
                            ->state(fn ($record) => $record->currentApproval()?->currentStepInstance()?->step?->name)
                            ->placeholder(__('filament-action-approvals::approval.infolist.not_available')),
                        TextEntry::make('currentStepStatus')
                            ->label(__('filament-action-approvals::approval.infolist.status'))
                            ->state(fn ($record) => $record->currentApproval()?->currentStepInstance()?->status)
                            ->badge()
                            ->placeholder(__('filament-action-approvals::approval.infolist.not_available')),
                        TextEntry::make('pending_approvers_display')
                            ->label(__('filament-action-approvals::approval.infolist.pending_approvers'))
                            ->state(function ($record): string {
                                $ids = $record->currentApproval()?->currentStepInstance()?->assigned_approver_ids;

                                if (empty($ids)) {
                                    return __('filament-action-approvals::approval.infolist.not_available');
                                }

                                return UserDisplayName::resolveMany(
                                    $ids,
                                    __('filament-action-approvals::approval.infolist.not_available'),
                                );
                            }),
                        TextEntry::make('currentStepProgress')
                            ->label(__('filament-action-approvals::approval.infolist.progress'))
                            ->state(fn ($record) => $record->currentApproval()?->currentStepInstance()?->received_approvals)
                            ->formatStateUsing(function ($state, $record): string {
                                $stepInstance = $record->currentApproval()?->currentStepInstance();

                                if (! $stepInstance) {
                                    return __('filament-action-approvals::approval.infolist.not_available');
                                }

                                return __('filament-action-approvals::approval.infolist.approvals_count', [
                                    'received' => $stepInstance->received_approvals,
                                    'required' => $stepInstance->required_approvals,
                                ]);
                            })
                            ->placeholder(__('filament-action-approvals::approval.infolist.not_available')),
                        DateDisplay::entry(
                            TextEntry::make('currentStepSlaDeadline')
                                ->label(__('filament-action-approvals::approval.infolist.sla_deadline'))
                                ->state(fn ($record) => $record->currentApproval()?->currentStepInstance()?->sla_deadline),
                        )
                            ->placeholder(__('filament-action-approvals::approval.infolist.no_sla'))
                            ->color(function ($state) {
                                if (! $state) {
                                    return null;
                                }

                                return $state->isPast() ? 'danger' : null;
                            }),
                    ])
                    ->columns(3)
                    ->visible(fn ($record): bool => $record->currentApproval()?->currentStepInstance() !== null),

                Section::make(__('filament-action-approvals::approval.infolist.recent_activity'))
                    ->schema([
                        RepeatableEntry::make('recentActivity')
                            ->hiddenLabel()
                            ->state(fn ($record) => $record->latestApproval()?->actions()->with('user')->get() ?? collect())
                            ->schema([
                                TextEntry::make('type')
                                    ->label(__('filament-action-approvals::approval.fields.type'))
                                    ->badge(),
                                TextEntry::make('actor_name')
                                    ->label(__('filament-action-approvals::approval.infolist.by'))
                                    ->state(fn (ApprovalAction $record): ?string => UserDisplayName::resolve($record->user))
                                    ->placeholder(__('filament-action-approvals::approval.infolist.system')),
                                TextEntry::make('comment')
                                    ->label(__('filament-action-approvals::approval.fields.comment'))
                                    ->placeholder(__('filament-action-approvals::approval.infolist.not_available')),
                                DateDisplay::entry(
                                    TextEntry::make('created_at')
                                        ->label(__('filament-action-approvals::approval.infolist.date')),
                                ),
                            ])
                            ->columns(4),
                    ])
                    ->collapsible()
                    ->visible(fn ($record): bool => $record->latestApproval()?->actions()->exists() ?? false),
            ])
            ->columns(3)
            ->collapsible()
            ->visible(fn (mixed $record): bool => $record instanceof Model && method_exists($record, 'latestApproval') && $record->latestApproval() !== null);
    }
}
