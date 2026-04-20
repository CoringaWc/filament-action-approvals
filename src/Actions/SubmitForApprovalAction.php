<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Actions;

use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Support\ApprovableActionLabel;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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
            ->authorize(function (self $action): bool {
                return $action->canRenderForRecord($action->resolveRecord());
            })
            ->visible(function (self $action): bool {
                return $action->canRenderForRecord($action->resolveRecord());
            })
            ->schema(function (self $action): array {
                $record = $action->resolveRecord();

                if (! $record) {
                    return [];
                }

                $flows = ApprovalFlow::forModel($record)->get();

                // When locked to a specific action key, skip the action selector entirely
                if ($action->lockedActionKey !== null) {
                    $matchingFlows = $action->getAvailableFlowsForActionKey($record, $flows, $action->lockedActionKey);

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

                $actionOptions = $action->getAvailableActionOptions($record, excludeCompleted: true);
                $hasGenericFlows = $action->getAvailableFlowsForActionKey($record, $flows)->isNotEmpty();
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
                            ->partiallyRenderComponentsAfterStateUpdated(['approval_flow_id'])
                            ->required()
                        : null,
                    Select::make('approval_flow_id')
                        ->label(__('filament-action-approvals::approval.actions.approval_flow'))
                        ->options(function (Get $get) use ($action, $flows, $record): array {
                            return $action->getAvailableFlowsForActionKey($record, $flows, $get('action_key'))
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->visible(function (Get $get) use ($action, $flows, $record): bool {
                            return $action->getAvailableFlowsForActionKey($record, $flows, $get('action_key'))->count() > 1;
                        })
                        ->required(function (Get $get) use ($action, $flows, $record): bool {
                            return $action->getAvailableFlowsForActionKey($record, $flows, $get('action_key'))->count() > 1;
                        }),
                ]));
            })
            ->action(function (self $action, array $data): void {
                $record = $action->resolveRecord();

                if (! $record) {
                    return;
                }

                $actionKey = $action->lockedActionKey ?? ($data['action_key'] ?? null);
                $flowId = $data['approval_flow_id'] ?? null;

                if (! $action->recordCanBeSubmittedForApproval($record, $actionKey)) {
                    Notification::make()
                        ->title(__('filament-action-approvals::approval.actions.submission_not_allowed'))
                        ->danger()
                        ->send();

                    return;
                }

                $flow = (is_int($flowId) || is_string($flowId))
                    ? ApprovalFlow::query()->find($flowId)
                    : null;

                app(ApprovalEngine::class)->submit($record, $flow, actionKey: $actionKey);

                Notification::make()
                    ->title(__('filament-action-approvals::approval.actions.submitted_success'))
                    ->success()
                    ->send();
            })
            ->after(function (self $action): void {
                $action->dispatchApprovalUpdated();
            })
            ->requiresConfirmation();
    }

    protected function resolveRecord(): ?Model
    {
        $record = $this->getRecord();

        return $record instanceof Model ? $record : null;
    }

    protected function canRenderForRecord(?Model $record): bool
    {
        if (! $record || ! method_exists($record, 'canBeSubmittedForApproval')) {
            return false;
        }

        $flows = ApprovalFlow::forModel($record)->get();

        if ($flows->isEmpty()) {
            return false;
        }

        if ($this->lockedActionKey !== null) {
            return $this->getAvailableFlowsForActionKey($record, $flows, $this->lockedActionKey)->isNotEmpty();
        }

        if ($this->getAvailableFlowsForActionKey($record, $flows)->isNotEmpty()) {
            return true;
        }

        return $this->getAvailableActionOptions($record, excludeCompleted: true) !== [];
    }

    /**
     * @return array<string, string>
     */
    protected function getAvailableActionOptions(Model $record, bool $excludeCompleted = false): array
    {
        if (! method_exists($record, 'canBeSubmittedForApproval')) {
            return [];
        }

        $completedKeys = $excludeCompleted ? Approval::completedActionKeysFor($record) : [];

        return array_filter(
            ApprovableActionLabel::optionsFor($record),
            function (string $_label, string $actionKey) use ($completedKeys, $record): bool {
                if (in_array($actionKey, $completedKeys, true)) {
                    return false;
                }

                return $this->recordCanBeSubmittedForApproval($record, $actionKey);
            },
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @param  Collection<int, ApprovalFlow>  $flows
     * @return Collection<int, ApprovalFlow>
     */
    protected function getAvailableFlowsForActionKey(Model $record, Collection $flows, ?string $actionKey = null): Collection
    {
        if (! $this->recordCanBeSubmittedForApproval($record, $actionKey)) {
            return collect();
        }

        if ($actionKey !== null && in_array($actionKey, Approval::completedActionKeysFor($record), true)) {
            return collect();
        }

        return ApprovalFlow::filterSubmissionFlows($flows, $actionKey);
    }

    protected function recordCanBeSubmittedForApproval(Model $record, ?string $actionKey = null): bool
    {
        if (! method_exists($record, 'canBeSubmittedForApproval')) {
            return false;
        }

        return $record->canBeSubmittedForApproval($actionKey);
    }

    protected function dispatchApprovalUpdated(): void
    {
        $livewire = $this->getLivewire();

        if (is_object($livewire) && method_exists($livewire, 'dispatch')) {
            $livewire->dispatch('filament-action-approvals::approval-updated');
        }
    }
}
