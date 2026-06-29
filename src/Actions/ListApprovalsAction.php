<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Actions;

use Closure;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Support\ApprovableModelLabel;
use CoringaWc\FilamentActionApprovals\Support\ApprovalModels;
use CoringaWc\FilamentActionApprovals\Support\CurrentPanelUser;
use CoringaWc\FilamentActionApprovals\Support\OperationalApprovalAuthorizer;
use CoringaWc\FilamentActionApprovals\Widgets\ContextualApprovalsTable;
use Filament\Actions\Action;
use Filament\Schemas\Components\Livewire;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ListApprovalsAction extends Action
{
    protected string|Closure|null $approvableType = null;

    protected Model|Closure|null $approvableRecord = null;

    /** @var array<string, mixed>|Closure */
    protected array|Closure $contextParameters = [];

    protected bool|Closure $shouldHideWhenNoActionableApprovals = true;

    protected ?bool $actionableApprovalsExist = null;

    /**
     * @return class-string<TableWidget>
     */
    protected function tableWidget(): string
    {
        return ContextualApprovalsTable::class;
    }

    public static function getDefaultName(): ?string
    {
        return 'listApprovals';
    }

    public function forApprovableType(string|Closure|null $approvableType): static
    {
        $this->approvableType = $approvableType;

        return $this;
    }

    public function forApprovable(Model|Closure|null $approvableRecord): static
    {
        $this->approvableRecord = $approvableRecord;

        return $this;
    }

    /**
     * @param  array<string, mixed>|Closure  $contextParameters
     */
    public function contextParameters(array|Closure $contextParameters): static
    {
        $this->contextParameters = $contextParameters;

        return $this;
    }

    public function hideWhenNoActionableApprovals(bool|Closure $condition = true): static
    {
        $this->shouldHideWhenNoActionableApprovals = $condition;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-action-approvals::approval.actions.list_approvals'))
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->color('gray')
            ->slideOver()
            ->schema([
                Livewire::make($this->tableWidget(), fn (): array => $this->getTableParameters())
                    ->key(fn (): string => $this->getTableKey()),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('filament-action-approvals::approval.relation_manager.close'))
            ->modalHeading(fn (): string => $this->resolveModalHeading())
            ->hidden(fn (self $action): bool => ! $action->hasApprovableContext()
                || ($action->shouldHideWhenNoActionableApprovals() && ! $action->hasActionableApprovals()));
    }

    /**
     * @return array<string, mixed>
     */
    protected function getTableParameters(): array
    {
        $record = $this->resolveApprovableRecord();

        if ($record instanceof Model) {
            return $this->withContextParameters([
                'approvableType' => $record->getMorphClass(),
                'approvableId' => $record->getKey(),
            ]);
        }

        $approvableType = $this->resolveApprovableType();

        return filled($approvableType)
            ? $this->withContextParameters(['approvableType' => $approvableType])
            : $this->withContextParameters([]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function withContextParameters(array $parameters): array
    {
        $contextParameters = $this->resolveContextParameters();

        if ($contextParameters === []) {
            return $parameters;
        }

        return [
            ...$parameters,
            'context' => $contextParameters,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveContextParameters(): array
    {
        $contextParameters = $this->evaluate($this->contextParameters);

        return is_array($contextParameters) ? $contextParameters : [];
    }

    protected function getTableKey(): string
    {
        $parameters = $this->getTableParameters();

        return 'contextual-approvals-table-'.md5($this->tableWidget().serialize($parameters));
    }

    protected function resolveModalHeading(): string
    {
        $record = $this->resolveApprovableRecord();

        if ($record instanceof Model) {
            return __('filament-action-approvals::approval.approval_context.record_scope', [
                'record' => ApprovableModelLabel::resolveRecord($record),
            ]);
        }

        $approvableType = $this->resolveApprovableType();

        if (filled($approvableType)) {
            return __('filament-action-approvals::approval.approval_context.model_scope', [
                'model' => ApprovableModelLabel::resolve($approvableType),
            ]);
        }

        return __('filament-action-approvals::approval.approvals');
    }

    protected function resolveApprovableRecord(): ?Model
    {
        $record = $this->evaluate($this->approvableRecord);

        if ($record instanceof Model) {
            return $record;
        }

        if (filled($this->resolveApprovableType())) {
            return null;
        }

        $record = $this->getRecord();

        if ($record instanceof Model) {
            return $record;
        }

        $livewire = $this->getLivewire();

        if (! is_object($livewire) || ! method_exists($livewire, 'getRecord')) {
            return null;
        }

        $record = $livewire->getRecord();

        return $record instanceof Model ? $record : null;
    }

    protected function hasApprovableContext(): bool
    {
        return $this->resolveApprovableRecord() instanceof Model
            || filled($this->resolveApprovableType());
    }

    protected function shouldHideWhenNoActionableApprovals(): bool
    {
        return (bool) $this->evaluate($this->shouldHideWhenNoActionableApprovals);
    }

    protected function hasActionableApprovals(): bool
    {
        if ($this->actionableApprovalsExist !== null) {
            return $this->actionableApprovalsExist;
        }

        $userId = CurrentPanelUser::id();

        if (! is_int($userId) && ! is_string($userId)) {
            return $this->actionableApprovalsExist = false;
        }

        $authorizer = app(OperationalApprovalAuthorizer::class);

        return $this->actionableApprovalsExist = $this->actionableApprovalsQuery($userId)
            ->get()
            ->contains(fn (Approval $approval): bool => $authorizer->canApprove($approval, $userId)
                || $authorizer->canReject($approval, $userId));
    }

    /**
     * @return Builder<Approval>
     */
    protected function actionableApprovalsQuery(int|string $userId): Builder
    {
        $query = ApprovalModels::approvalQuery()
            ->withOperationalRelations()
            ->with([
                'stepInstances.actions',
                'stepInstances.delegations',
            ]);

        $record = $this->resolveApprovableRecord();

        if ($record instanceof Model) {
            $query->forApprovable($record);
        } else {
            $approvableType = $this->resolveApprovableType();

            if (blank($approvableType)) {
                return $query->whereRaw('1 = 0');
            }

            $query->forApprovableType($approvableType);
        }

        if (FilamentActionApprovalsPlugin::isSuperAdmin($userId)) {
            return $query->withStatus(ApprovalStatus::Pending);
        }

        return $query->awaitingUserAction($userId);
    }

    protected function resolveApprovableType(): ?string
    {
        $approvableType = $this->evaluate($this->approvableType);

        return is_string($approvableType) && filled($approvableType)
            ? $approvableType
            : null;
    }
}
