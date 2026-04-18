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
use Illuminate\Database\Eloquent\Model;

class SubmitForApprovalAction extends Action
{
    protected ?string $lockedActionKey = null;

    public static function getDefaultName(): ?string
    {
        return 'submitForApproval';
    }

    /**
     * Pre-configure this action with a specific action key.
     *
     * When set, the action key selector is hidden and the submission
     * is locked to this specific approvable action. This allows you
     * to create dedicated buttons for each approvable action:
     *
     *     SubmitForApprovalAction::make('submitPO')
     *         ->actionKey('submit')
     *         ->label('Submit for Approval')
     */
    public function actionKey(string $actionKey): static
    {
        $this->lockedActionKey = $actionKey;

        return $this;
    }

    public function getLockedActionKey(): ?string
    {
        return $this->lockedActionKey;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-action-approvals::approval.actions.submit'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('info')
            ->visible(function (): bool {
                $record = $this->resolveRecord();

                if (! $record || ! method_exists($record, 'canBeSubmittedForApproval')) {
                    return false;
                }

                if (! $record->canBeSubmittedForApproval()) {
                    return false;
                }

                $flows = ApprovalFlow::forModel($record)->get();

                if ($flows->isEmpty()) {
                    return false;
                }

                // When locked to a specific action key, only show if there are matching flows
                if ($this->lockedActionKey !== null) {
                    return ApprovalFlow::filterSubmissionFlows($flows, $this->lockedActionKey)->isNotEmpty();
                }

                $hasGenericFlows = $flows->whereNull('action_key')->isNotEmpty();
                $hasSpecificFlows = $flows->whereNotNull('action_key')->isNotEmpty();
                $canResolveSpecificActions = ApprovableActionLabel::hasOptionsFor($record);

                return $hasGenericFlows || (! $hasSpecificFlows) || $canResolveSpecificActions;
            })
            ->schema(function (): array {
                $record = $this->resolveRecord();

                if (! $record) {
                    return [];
                }

                $flows = ApprovalFlow::forModel($record)->get();

                // When locked to a specific action key, skip the action selector entirely
                if ($this->lockedActionKey !== null) {
                    $matchingFlows = ApprovalFlow::filterSubmissionFlows($flows, $this->lockedActionKey);

                    if ($matchingFlows->count() <= 1) {
                        return [];
                    }

                    return [
                        Select::make('approval_flow_id')
                            ->label(__('filament-action-approvals::approval.actions.approval_flow'))
                            ->options($matchingFlows->pluck('name', 'id')->all())
                            ->required(),
                    ];
                }

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
                $record = $this->resolveRecord();

                if (! $record) {
                    return;
                }

                $actionKey = $this->lockedActionKey ?? ($data['action_key'] ?? null);
                $flowId = $data['approval_flow_id'] ?? null;

                $flow = (is_int($flowId) || is_string($flowId))
                    ? ApprovalFlow::query()->find($flowId)
                    : null;

                app(ApprovalEngine::class)->submit($record, $flow, actionKey: $actionKey);

                Notification::make()
                    ->title(__('filament-action-approvals::approval.actions.submitted_success'))
                    ->success()
                    ->send();
            })
            ->after(function (): void {
                $this->dispatchApprovalUpdated();
            })
            ->requiresConfirmation();
    }

    protected function resolveRecord(): ?Model
    {
        $record = $this->getRecord();

        return $record instanceof Model ? $record : null;
    }

    protected function dispatchApprovalUpdated(): void
    {
        $livewire = $this->getLivewire();

        if (is_object($livewire) && method_exists($livewire, 'dispatch')) {
            $livewire->dispatch('filament-action-approvals::approval-updated');
        }
    }
}
