<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Actions;

use Closure;
use CoringaWc\FilamentActionApprovals\Support\ApprovableModelLabel;
use CoringaWc\FilamentActionApprovals\Widgets\ContextualApprovalsTable;
use Filament\Actions\Action;
use Filament\Schemas\Components\Livewire;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class ListApprovalsAction extends Action
{
    protected string|Closure|null $approvableType = null;

    protected Model|Closure|null $approvableRecord = null;

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

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-action-approvals::approval.actions.list_approvals'))
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->color('gray')
            ->slideOver()
            ->schema([
                Livewire::make(ContextualApprovalsTable::class, fn (): array => $this->getTableParameters())
                    ->key(fn (): string => $this->getTableKey()),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('filament-action-approvals::approval.relation_manager.close'))
            ->modalHeading(fn (): string => $this->resolveModalHeading())
            ->visible(fn (): bool => $this->resolveApprovableRecord() instanceof Model || filled($this->resolveApprovableType()));
    }

    /**
     * @return array<string, int|string>
     */
    protected function getTableParameters(): array
    {
        $record = $this->resolveApprovableRecord();

        if ($record instanceof Model) {
            return [
                'approvableType' => $record->getMorphClass(),
                'approvableId' => $record->getKey(),
            ];
        }

        $approvableType = $this->resolveApprovableType();

        return filled($approvableType)
            ? ['approvableType' => $approvableType]
            : [];
    }

    protected function getTableKey(): string
    {
        $parameters = $this->getTableParameters();

        return 'contextual-approvals-table-'.md5(serialize($parameters));
    }

    protected function resolveModalHeading(): string
    {
        $record = $this->resolveApprovableRecord();

        if ($record instanceof Model) {
            return __('filament-action-approvals::approval.approval_context.record_scope', [
                'record' => ApprovableModelLabel::resolveWithKey($record->getMorphClass(), $record->getKey()),
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

        return $record instanceof Model ? $record : null;
    }

    protected function resolveApprovableType(): ?string
    {
        $approvableType = $this->evaluate($this->approvableType);

        return is_string($approvableType) && filled($approvableType)
            ? $approvableType
            : null;
    }
}
