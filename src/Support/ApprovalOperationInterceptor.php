<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use CoringaWc\FilamentActionApprovals\Attributes\ApprovableOperation;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalOperation;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Events\ActionCalling;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

class ApprovalOperationInterceptor
{
    private static ?int $registeredApplicationId = null;

    public function register(): void
    {
        $applicationId = spl_object_id(app());

        if (self::$registeredApplicationId === $applicationId) {
            return;
        }

        EditAction::configureUsing(fn (EditAction $action): EditAction => app(self::class)->configureEditAction($action));
        DeleteAction::configureUsing(fn (DeleteAction $action): DeleteAction => app(self::class)->configureDeleteAction($action));

        Event::listen(ActionCalling::class, fn (mixed $event): null => app(self::class)->handleActionCalling($event));

        self::$registeredApplicationId = $applicationId;
    }

    public function configureEditAction(EditAction $action): EditAction
    {
        return $action
            ->modalContent(fn (?Model $record = null) => $this->shouldSubmitApprovalRequest($record, ApprovalOperation::Update)
                ? FilamentActionApprovalsPlugin::approvalRequestModalContent()
                : null)
            ->modalSubmitActionLabel(fn (?Model $record = null): string => $this->shouldSubmitApprovalRequest($record, ApprovalOperation::Update)
                ? __('filament-action-approvals::approval.actions.submit_approval_request')
                : __('filament-actions::edit.single.modal.actions.save.label'))
            ->successNotificationTitle(fn (?Model $record = null): string => $this->shouldSubmitApprovalRequest($record, ApprovalOperation::Update)
                ? __('filament-action-approvals::approval.actions.approval_request_submitted')
                : __('filament-actions::edit.single.notifications.saved.title'));
    }

    public function configureDeleteAction(DeleteAction $action): DeleteAction
    {
        return $action
            ->label(fn (?Model $record = null): string => $this->shouldSubmitApprovalRequest($record, ApprovalOperation::Delete)
                ? __('filament-action-approvals::approval.actions.request_delete')
                : __('filament-actions::delete.single.label'))
            ->modalHeading(fn (?Model $record = null): string => $this->shouldSubmitApprovalRequest($record, ApprovalOperation::Delete)
                ? __('filament-action-approvals::approval.actions.request_delete_heading')
                : __('filament-actions::delete.single.modal.heading', ['label' => $action->getRecordTitle()]))
            ->modalContent(fn (?Model $record = null) => $this->shouldSubmitApprovalRequest($record, ApprovalOperation::Delete)
                ? FilamentActionApprovalsPlugin::approvalRequestModalContent()
                : null)
            ->modalSubmitActionLabel(fn (?Model $record = null): string => $this->shouldSubmitApprovalRequest($record, ApprovalOperation::Delete)
                ? __('filament-action-approvals::approval.actions.submit_approval_request')
                : __('filament-actions::delete.single.modal.actions.delete.label'))
            ->successNotificationTitle(fn (?Model $record = null): string => $this->shouldSubmitApprovalRequest($record, ApprovalOperation::Delete)
                ? __('filament-action-approvals::approval.actions.approval_request_submitted')
                : __('filament-actions::delete.single.notifications.deleted.title'));
    }

    public function handleActionCalling(mixed $event): null
    {
        $action = $event instanceof ActionCalling ? $event->getAction() : $event;

        if (! $action instanceof Action) {
            return null;
        }

        $record = $action->getRecord();

        if (! $record instanceof Model) {
            return null;
        }

        if ($action instanceof EditAction) {
            $this->processEditAction($action, $record, $this->editActionSubmissionData($action));

            return null;
        }

        if ($action instanceof DeleteAction) {
            $this->processDeleteAction($action, $record);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function processEditAction(EditAction $action, Model $record, array $data): void
    {
        if (! FilamentActionApprovalsPlugin::shouldInterceptOperationsForCurrentPanel()) {
            return;
        }

        if (! method_exists($record, 'approvalOperationDefinitionForData') || ! method_exists($record, 'approvalPayloadForOperationDefinition') || ! method_exists($record, 'submitApprovalForOperationDefinition')) {
            return;
        }

        $operation = ApprovalOperation::Update;

        try {
            $definition = $record->approvalOperationDefinitionForData($operation, $data);
        } catch (ValidationException $exception) {
            $this->haltWithFailure($action, $this->validationMessage($exception));

            return;
        }

        if (! $definition instanceof ApprovableOperation) {
            return;
        }

        $actionKey = $definition->normalizedActionKey($record);

        if (! filled($actionKey)) {
            return;
        }

        $flow = $this->findOperationApprovalFlow($record, $actionKey);

        if (! $flow instanceof ApprovalFlow) {
            return;
        }

        if (FilamentActionApprovalsPlugin::canApplyDirectly(CurrentPanelUser::id())) {
            return;
        }

        $payload = $record->approvalPayloadForOperationDefinition($definition, $operation, $data);

        if ($payload === []) {
            $this->haltWithWarning($action, __('filament-action-approvals::approval.actions.no_changes_to_approve'));

            return;
        }

        try {
            $record->submitApprovalForOperationDefinition($operation, $definition, $data);
        } catch (ValidationException $exception) {
            $this->haltWithFailure($action, $this->validationMessage($exception));

            return;
        }

        $this->sendApprovalRequestSubmittedNotification($action);

        $action->cancel();
    }

    private function processDeleteAction(DeleteAction $action, Model $record): void
    {
        if (! FilamentActionApprovalsPlugin::shouldInterceptOperationsForCurrentPanel()) {
            return;
        }

        if (! method_exists($record, 'approvalActionKeyForOperation') || ! method_exists($record, 'submitApproval')) {
            return;
        }

        $operation = ApprovalOperation::Delete;
        $actionKey = $record->approvalActionKeyForOperation($operation);

        if (! filled($actionKey)) {
            return;
        }

        $flow = $this->findOperationApprovalFlow($record, $actionKey);

        if (! $flow instanceof ApprovalFlow) {
            return;
        }

        if (FilamentActionApprovalsPlugin::canApplyDirectly(CurrentPanelUser::id())) {
            return;
        }

        try {
            $record->submitApproval($operation);
        } catch (ValidationException $exception) {
            $this->haltWithFailure($action, $this->validationMessage($exception));

            return;
        }

        $this->sendApprovalRequestSubmittedNotification($action);

        $action->cancel();
    }

    private function sendApprovalRequestSubmittedNotification(EditAction|DeleteAction $action): void
    {
        $action->successNotification(null);

        Notification::make()
            ->success()
            ->title(__('filament-action-approvals::approval.actions.approval_request_submitted'))
            ->send();
    }

    private function shouldSubmitApprovalRequest(?Model $record, ApprovalOperation|string $operation): bool
    {
        if (! FilamentActionApprovalsPlugin::shouldInterceptOperationsForCurrentPanel()) {
            return false;
        }

        if (! $record instanceof Model || ! method_exists($record, 'approvalActionKeysForOperation')) {
            return false;
        }

        if (FilamentActionApprovalsPlugin::canApplyDirectly(CurrentPanelUser::id())) {
            return false;
        }

        foreach ($record->approvalActionKeysForOperation($operation) as $actionKey) {
            if (filled($actionKey) && $this->findOperationApprovalFlow($record, $actionKey) instanceof ApprovalFlow) {
                return true;
            }
        }

        return false;
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

    /**
     * @return array<string, mixed>
     */
    private function editActionSubmissionData(EditAction $action): array
    {
        $rawData = $action->getRawData();

        return array_replace_recursive($rawData, $action->getData());
    }

    private function findOperationApprovalFlow(Model $record, string $actionKey): ?ApprovalFlow
    {
        $flowModel = ApprovalModels::flow();

        return $flowModel::forAction($record, $actionKey)->first();
    }
}
