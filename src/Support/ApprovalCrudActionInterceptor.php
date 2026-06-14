<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use CoringaWc\FilamentActionApprovals\Contracts\InterceptsApprovalCrudActions;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final class ApprovalCrudActionInterceptor
{
    private static ?int $registeredApplicationId = null;

    public function __construct(
        private readonly ApprovalCrudPayload $payloadBuilder,
    ) {}

    public function register(): void
    {
        $applicationId = spl_object_id(app());

        if (self::$registeredApplicationId === $applicationId) {
            return;
        }

        EditAction::configureUsing(fn (EditAction $action): EditAction => app(self::class)->configureEditAction($action));
        DeleteAction::configureUsing(fn (DeleteAction $action): DeleteAction => app(self::class)->configureDeleteAction($action));

        self::$registeredApplicationId = $applicationId;
    }

    public function configureEditAction(EditAction $action): EditAction
    {
        return $action
            ->modalContent(fn (?Model $record = null) => $this->shouldSubmitApprovalRequest($record, InterceptsApprovalCrudActions::OperationEdit)
                ? FilamentActionApprovalsPlugin::approvalRequestModalContent()
                : null)
            ->modalSubmitActionLabel(fn (?Model $record = null): string => $this->shouldSubmitApprovalRequest($record, InterceptsApprovalCrudActions::OperationEdit)
                ? __('filament-action-approvals::approval.actions.submit_approval_request')
                : __('filament-actions::edit.single.modal.actions.save.label'))
            ->successNotificationTitle(fn (?Model $record = null): string => $this->shouldSubmitApprovalRequest($record, InterceptsApprovalCrudActions::OperationEdit)
                ? __('filament-action-approvals::approval.actions.approval_request_submitted')
                : __('filament-actions::edit.single.notifications.saved.title'))
            ->using(function (EditAction $action, array $data, HasActions&HasSchemas $livewire, Model $record, ?Table $table): void {
                $this->processEditAction($action, $record, $data, $livewire, $table);
            });
    }

    public function configureDeleteAction(DeleteAction $action): DeleteAction
    {
        return $action
            ->label(fn (?Model $record = null): string => $this->shouldSubmitApprovalRequest($record, InterceptsApprovalCrudActions::OperationDelete)
                ? __('filament-action-approvals::approval.actions.request_delete')
                : __('filament-actions::delete.single.label'))
            ->modalHeading(fn (?Model $record = null): string => $this->shouldSubmitApprovalRequest($record, InterceptsApprovalCrudActions::OperationDelete)
                ? __('filament-action-approvals::approval.actions.request_delete_heading')
                : __('filament-actions::delete.single.modal.heading', ['label' => $action->getRecordTitle()]))
            ->modalContent(fn (?Model $record = null) => $this->shouldSubmitApprovalRequest($record, InterceptsApprovalCrudActions::OperationDelete)
                ? FilamentActionApprovalsPlugin::approvalRequestModalContent()
                : null)
            ->modalSubmitActionLabel(fn (?Model $record = null): string => $this->shouldSubmitApprovalRequest($record, InterceptsApprovalCrudActions::OperationDelete)
                ? __('filament-action-approvals::approval.actions.submit_approval_request')
                : __('filament-actions::delete.single.modal.actions.delete.label'))
            ->successNotificationTitle(fn (?Model $record = null): string => $this->shouldSubmitApprovalRequest($record, InterceptsApprovalCrudActions::OperationDelete)
                ? __('filament-action-approvals::approval.actions.approval_request_submitted')
                : __('filament-actions::delete.single.notifications.deleted.title'))
            ->using(function (DeleteAction $action, Model $record): ?bool {
                return $this->processDeleteAction($action, $record);
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function processEditAction(EditAction $action, Model $record, array $data, HasActions&HasSchemas $livewire, ?Table $table): void
    {
        if (! FilamentActionApprovalsPlugin::shouldInterceptCrudActionsForCurrentPanel()) {
            $this->performDefaultEdit($record, $data, $livewire, $table);

            return;
        }

        if (! $record instanceof InterceptsApprovalCrudActions) {
            $this->performDefaultEdit($record, $data, $livewire, $table);

            return;
        }

        $actionKey = $record->approvalCrudActionKey(InterceptsApprovalCrudActions::OperationEdit);

        if (! filled($actionKey)) {
            $this->performDefaultEdit($record, $data, $livewire, $table);

            return;
        }

        $flow = $this->findCrudApprovalFlow($record, $actionKey);

        if (! $flow instanceof ApprovalFlow) {
            $this->haltWithFailure($action, __('filament-action-approvals::approval.actions.approval_flow_missing'));

            return;
        }

        if (FilamentActionApprovalsPlugin::canApplyDirectly(CurrentPanelUser::id())) {
            $this->performDefaultEdit($record, $data, $livewire, $table);

            return;
        }

        $payload = $this->payloadBuilder->editPayload($record, $data, $record->approvalCrudFields(InterceptsApprovalCrudActions::OperationEdit));

        if ($payload === []) {
            $this->haltWithWarning($action, __('filament-action-approvals::approval.actions.no_changes_to_approve'));

            return;
        }

        try {
            app(ApprovalEngine::class)->submit(
                approvable: $record,
                flow: $flow,
                submittedBy: CurrentPanelUser::id(),
                actionKey: $actionKey,
                metadata: [
                    'payload' => $payload,
                    'crud' => [
                        'operation' => InterceptsApprovalCrudActions::OperationEdit,
                    ],
                ],
            );
        } catch (ValidationException $exception) {
            $this->haltWithFailure($action, $this->validationMessage($exception));

            return;
        }

        $this->sendApprovalRequestSubmittedNotification($action);
    }

    private function processDeleteAction(DeleteAction $action, Model $record): ?bool
    {
        if (! FilamentActionApprovalsPlugin::shouldInterceptCrudActionsForCurrentPanel()) {
            return $record->delete();
        }

        if (! $record instanceof InterceptsApprovalCrudActions) {
            return $record->delete();
        }

        $actionKey = $record->approvalCrudActionKey(InterceptsApprovalCrudActions::OperationDelete);

        if (! filled($actionKey)) {
            return $record->delete();
        }

        $flow = $this->findCrudApprovalFlow($record, $actionKey);

        if (! $flow instanceof ApprovalFlow) {
            $this->haltWithFailure($action, __('filament-action-approvals::approval.actions.approval_flow_missing'));

            return false;
        }

        if (FilamentActionApprovalsPlugin::canApplyDirectly(CurrentPanelUser::id())) {
            return $record->delete();
        }

        try {
            app(ApprovalEngine::class)->submit(
                approvable: $record,
                flow: $flow,
                submittedBy: CurrentPanelUser::id(),
                actionKey: $actionKey,
                metadata: [
                    'payload' => $this->payloadBuilder->deletePayload(),
                    'crud' => [
                        'operation' => InterceptsApprovalCrudActions::OperationDelete,
                    ],
                ],
            );
        } catch (ValidationException $exception) {
            $this->haltWithFailure($action, $this->validationMessage($exception));

            return false;
        }

        $this->sendApprovalRequestSubmittedNotification($action);

        return true;
    }

    private function sendApprovalRequestSubmittedNotification(EditAction|DeleteAction $action): void
    {
        $action->successNotification(null);

        Notification::make()
            ->success()
            ->title(__('filament-action-approvals::approval.actions.approval_request_submitted'))
            ->send();
    }

    private function shouldSubmitApprovalRequest(?Model $record, string $operation): bool
    {
        if (! FilamentActionApprovalsPlugin::shouldInterceptCrudActionsForCurrentPanel()) {
            return false;
        }

        if (! $record instanceof InterceptsApprovalCrudActions) {
            return false;
        }

        if (FilamentActionApprovalsPlugin::canApplyDirectly(CurrentPanelUser::id())) {
            return false;
        }

        return filled($record->approvalCrudActionKey($operation));
    }

    private function haltWithFailure(EditAction|DeleteAction $action, string $title): void
    {
        $action->successNotification(null);

        Notification::make()
            ->danger()
            ->title($title)
            ->send();

        $action->halt();
    }

    private function haltWithWarning(EditAction|DeleteAction $action, string $title): void
    {
        $action->successNotification(null);

        Notification::make()
            ->warning()
            ->title($title)
            ->send();

        $action->halt();
    }

    private function validationMessage(ValidationException $exception): string
    {
        $message = collect($exception->errors())->flatten()->first();

        return is_string($message) && filled($message)
            ? $message
            : __('filament-action-approvals::approval.actions.apply_failed');
    }

    private function findCrudApprovalFlow(Model $record, string $actionKey): ?ApprovalFlow
    {
        $flowModel = ApprovalModels::flow();

        return $flowModel::forAction($record, $actionKey)->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function performDefaultEdit(Model $record, array $data, HasActions&HasSchemas $livewire, ?Table $table): void
    {
        $relationship = $table?->getRelationship();
        $translatableContentDriver = $livewire->makeFilamentTranslatableContentDriver();

        if ($relationship instanceof BelongsToMany) {
            $pivot = $record->getRelationValue($relationship->getPivotAccessor());
            $pivotColumns = $relationship->getPivotColumns();
            $pivotData = Arr::only($data, $pivotColumns);

            if (count($pivotColumns) > 0) {
                if ($translatableContentDriver) {
                    $translatableContentDriver->updateRecord($pivot, $pivotData);
                } else {
                    $pivot->update($pivotData);
                }
            }

            $data = Arr::except($data, $pivotColumns);
        }

        if ($translatableContentDriver) {
            $translatableContentDriver->updateRecord($record, $data);

            return;
        }

        $record->update($data);
    }
}
