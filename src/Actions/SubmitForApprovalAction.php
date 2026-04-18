<?php

namespace CoringaWc\FilamentActionApprovals\Actions;

use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Support\ApprovableActionLabel;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

class SubmitForApprovalAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'submitForApproval';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-action-approvals::approval.actions.submit'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('info')
            ->visible(function (): bool {
                $record = $this->getRecord();

                if (! method_exists($record, 'canBeSubmittedForApproval')) {
                    return false;
                }

                $flows = ApprovalFlow::forModel($record)->get();
                $hasGenericFlows = $flows->whereNull('action_key')->isNotEmpty();
                $hasSpecificFlows = $flows->whereNotNull('action_key')->isNotEmpty();
                $canResolveSpecificActions = ApprovableActionLabel::hasOptionsFor($record);

                return $record->canBeSubmittedForApproval()
                    && $flows->isNotEmpty()
                    && ($hasGenericFlows || (! $hasSpecificFlows) || $canResolveSpecificActions);
            })
            ->schema(function (): array {
                $record = $this->getRecord();
                $flows = ApprovalFlow::forModel($record)->get();
                $actionOptions = ApprovableActionLabel::optionsFor($record);
                $hasGenericFlows = $flows->whereNull('action_key')->isNotEmpty();
                $hasSpecificFlows = $flows->whereNotNull('action_key')->isNotEmpty();
                $shouldAskForAction = ($actionOptions !== []) && ($hasSpecificFlows || (! $hasGenericFlows));

                if ((! $shouldAskForAction) && ($flows->count() <= 1)) {
                    return [];
                }

                return array_values(array_filter([
                    $shouldAskForAction
                        ? Select::make('action_key')
                            ->label(__('filament-action-approvals::approval.actions.approval_action'))
                            ->options($actionOptions)
                            ->helperText(__('filament-action-approvals::approval.actions.approval_action_helper'))
                            ->live()
                            ->required()
                        : null,
                    Select::make('approval_flow_id')
                        ->label(__('filament-action-approvals::approval.actions.approval_flow'))
                        ->options(function (Get $get) use ($flows): array {
                            return ApprovalFlow::filterSubmissionFlows($flows, $get('action_key'))
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->visible(function (Get $get) use ($flows): bool {
                            return ApprovalFlow::filterSubmissionFlows($flows, $get('action_key'))->count() > 1;
                        })
                        ->required(function (Get $get) use ($flows): bool {
                            return ApprovalFlow::filterSubmissionFlows($flows, $get('action_key'))->count() > 1;
                        }),
                ]));
            })
            ->action(function (array $data): void {
                $record = $this->getRecord();
                $flow = isset($data['approval_flow_id'])
                    ? ApprovalFlow::find($data['approval_flow_id'])
                    : null;

                app(ApprovalEngine::class)->submit($record, $flow, actionKey: $data['action_key'] ?? null);

                Notification::make()
                    ->title(__('filament-action-approvals::approval.actions.submitted_success'))
                    ->success()
                    ->send();
            })
            ->after(function (): void {
                $this->getLivewire()->dispatch('filament-action-approvals::approval-updated');
            })
            ->requiresConfirmation();
    }
}
