<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use CoringaWc\FilamentActionApprovals\Enums\ApprovalOperation;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
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
                : __('filament-actions::edit.single.notifications.saved.title'))
            ->using(function (EditAction $action, array $data, HasActions&HasSchemas $livewire, Model $record, ?Table $table): void {
                $this->processEditAction($action, $record, $data, $livewire, $table);
            });
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
        if (! FilamentActionApprovalsPlugin::shouldInterceptOperationsForCurrentPanel()) {
            $this->performDefaultEdit($record, $data, $livewire, $table);

            return;
        }

        if (! method_exists($record, 'approvalActionKeyForOperation') || ! method_exists($record, 'approvalPayloadForOperation') || ! method_exists($record, 'submitApproval')) {
            $this->performDefaultEdit($record, $data, $livewire, $table);

            return;
        }

        $operation = ApprovalOperation::Update;
        $actionKey = $record->approvalActionKeyForOperation($operation);

        if (! filled($actionKey)) {
            $this->performDefaultEdit($record, $data, $livewire, $table);

            return;
        }

        $flow = $this->findOperationApprovalFlow($record, $actionKey);

        if (! $flow instanceof ApprovalFlow) {
            $this->performDefaultEdit($record, $data, $livewire, $table);

            return;
        }

        if (FilamentActionApprovalsPlugin::canApplyDirectly(CurrentPanelUser::id())) {
            $this->performDefaultEdit($record, $data, $livewire, $table);

            return;
        }

        $payload = $record->approvalPayloadForOperation($operation, $data);

        if ($payload === []) {
            $this->haltWithWarning($action, __('filament-action-approvals::approval.actions.no_changes_to_approve'));

            return;
        }

        try {
            $record->submitApproval($operation, $payload);
        } catch (ValidationException $exception) {
            $this->haltWithFailure($action, $this->validationMessage($exception));

            return;
        }

        $this->sendApprovalRequestSubmittedNotification($action);
    }

    private function processDeleteAction(DeleteAction $action, Model $record): ?bool
    {
        if (! FilamentActionApprovalsPlugin::shouldInterceptOperationsForCurrentPanel()) {
            return $record->delete();
        }

        if (! method_exists($record, 'approvalActionKeyForOperation') || ! method_exists($record, 'submitApproval')) {
            return $record->delete();
        }

        $operation = ApprovalOperation::Delete;
        $actionKey = $record->approvalActionKeyForOperation($operation);

        if (! filled($actionKey)) {
            return $record->delete();
        }

        $flow = $this->findOperationApprovalFlow($record, $actionKey);

        if (! $flow instanceof ApprovalFlow) {
            return $record->delete();
        }

        if (FilamentActionApprovalsPlugin::canApplyDirectly(CurrentPanelUser::id())) {
            return $record->delete();
        }

        try {
            $record->submitApproval($operation);
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

    private function shouldSubmitApprovalRequest(?Model $record, ApprovalOperation|string $operation): bool
    {
        if (! FilamentActionApprovalsPlugin::shouldInterceptOperationsForCurrentPanel()) {
            return false;
        }

        if (! $record instanceof Model || ! method_exists($record, 'approvalActionKeyForOperation')) {
            return false;
        }

        if (FilamentActionApprovalsPlugin::canApplyDirectly(CurrentPanelUser::id())) {
            return false;
        }

        $actionKey = $record->approvalActionKeyForOperation($operation);

        if (! filled($actionKey)) {
            return false;
        }

        return $this->findOperationApprovalFlow($record, $actionKey) instanceof ApprovalFlow;
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

    private function findOperationApprovalFlow(Model $record, string $actionKey): ?ApprovalFlow
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
