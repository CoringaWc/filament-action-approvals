<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use CoringaWc\FilamentActionApprovals\Actions\ApproveAction;
use CoringaWc\FilamentActionApprovals\Actions\CommentAction;
use CoringaWc\FilamentActionApprovals\Actions\DelegateAction;
use CoringaWc\FilamentActionApprovals\Actions\RejectAction;
use CoringaWc\FilamentActionApprovals\Actions\SubmitForApprovalAction;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class ApprovalActionGroup
{
    public static function make(bool $includeSubmit = true, bool $hideWhenEmpty = true): ActionGroup
    {
        $actionGroup = ActionGroup::make(static::actions($includeSubmit))
            ->label(__('filament-action-approvals::approval.approvals'))
            ->icon(Heroicon::EllipsisVertical)
            ->color('gray')
            ->button()
            ->dropdownWidth(Width::Medium);

        if ($hideWhenEmpty) {
            $actionGroup->visible(fn (): bool => static::hasVisibleActions(static::resolveActionGroupRecord($actionGroup), $includeSubmit));
        }

        return $actionGroup;
    }

    public static function hasVisibleActions(?Model $record, bool $includeSubmit = true): bool
    {
        if (! $record instanceof Model) {
            return false;
        }

        if ($includeSubmit && static::canSubmitForApproval($record)) {
            return true;
        }

        $userId = auth()->id();
        $approval = static::resolveCurrentApproval($record);

        if ($userId === null || ! $approval instanceof Approval) {
            return false;
        }

        return (FilamentActionApprovalsPlugin::isOperationalActionEnabled('approve') && $approval->canBeApprovedBy($userId))
            || (FilamentActionApprovalsPlugin::isOperationalActionEnabled('reject') && $approval->canBeRejectedBy($userId))
            || (FilamentActionApprovalsPlugin::isOperationalActionEnabled('comment', false) && $approval->canReceiveCommentsFrom($userId))
            || (FilamentActionApprovalsPlugin::isOperationalActionEnabled('delegate', false) && $approval->canBeDelegatedBy($userId));
    }

    /**
     * @return array<Action>
     */
    protected static function actions(bool $includeSubmit): array
    {
        return array_values(array_filter([
            $includeSubmit ? SubmitForApprovalAction::make() : null,
            FilamentActionApprovalsPlugin::isOperationalActionEnabled('approve') ? ApproveAction::make() : null,
            FilamentActionApprovalsPlugin::isOperationalActionEnabled('reject') ? RejectAction::make() : null,
            FilamentActionApprovalsPlugin::isOperationalActionEnabled('comment', false) ? CommentAction::make() : null,
            FilamentActionApprovalsPlugin::isOperationalActionEnabled('delegate', false) ? DelegateAction::make() : null,
        ]));
    }

    protected static function canSubmitForApproval(Model $record): bool
    {
        if (! method_exists($record, 'canBeSubmittedForApproval')) {
            return false;
        }

        if (! $record->canBeSubmittedForApproval()) {
            return false;
        }

        $flows = ApprovalFlow::forModel($record)->get();

        if ($flows->isEmpty()) {
            return false;
        }

        $hasGenericFlows = $flows->whereNull('action_key')->isNotEmpty();
        $hasSpecificFlows = $flows->whereNotNull('action_key')->isNotEmpty();
        $canResolveSpecificActions = ApprovableActionLabel::hasOptionsFor($record);

        return $hasGenericFlows || (! $hasSpecificFlows) || $canResolveSpecificActions;
    }

    protected static function resolveCurrentApproval(Model $record): ?Approval
    {
        if ($record instanceof Approval) {
            return $record;
        }

        if (! method_exists($record, 'currentApproval')) {
            return null;
        }

        /** @var ?Approval $approval */
        $approval = $record->currentApproval();

        return $approval;
    }

    protected static function resolveActionGroupRecord(ActionGroup $actionGroup): ?Model
    {
        $record = $actionGroup->getRecord();

        if ($record instanceof Model) {
            return $record;
        }

        $livewire = $actionGroup->getLivewire();

        if (! is_object($livewire) || ! method_exists($livewire, 'getRecord')) {
            return null;
        }

        $record = $livewire->getRecord();

        return $record instanceof Model ? $record : null;
    }
}
