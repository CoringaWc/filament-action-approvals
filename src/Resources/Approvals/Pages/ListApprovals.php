<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Resources\Approvals\Pages;

use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Resources\Approvals\ApprovalResource;
use CoringaWc\FilamentActionApprovals\Support\ApprovableModelLabel;
use CoringaWc\FilamentActionApprovals\Widgets\ApprovalAnalyticsWidget;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListApprovals extends ListRecords
{
    protected static string $resource = ApprovalResource::class;

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make(__('filament-action-approvals::approval.tabs.pending'))
                ->badge($this->getStatusCount(ApprovalStatus::Pending))
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ApprovalStatus::Pending->value)),
            'approved' => Tab::make(__('filament-action-approvals::approval.tabs.approved'))
                ->badge($this->getStatusCount(ApprovalStatus::Approved))
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ApprovalStatus::Approved->value)),
            'rejected' => Tab::make(__('filament-action-approvals::approval.tabs.rejected'))
                ->badge($this->getStatusCount(ApprovalStatus::Rejected))
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ApprovalStatus::Rejected->value)),
            'cancelled' => Tab::make(__('filament-action-approvals::approval.tabs.cancelled'))
                ->badge($this->getStatusCount(ApprovalStatus::Cancelled))
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ApprovalStatus::Cancelled->value)),
            'all' => Tab::make(__('filament-action-approvals::approval.tabs.all'))
                ->badge($this->getAllCount()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'pending';
    }

    public function getSubheading(): ?string
    {
        if (! $this->hasContextualScope()) {
            return null;
        }

        if ($this->getContextualApprovableId() !== null) {
            return __('filament-action-approvals::approval.approval_context.record_scope', [
                'record' => ApprovableModelLabel::resolveWithKey(
                    $this->getContextualApprovableType(),
                    $this->getContextualApprovableId(),
                ),
            ]);
        }

        return __('filament-action-approvals::approval.approval_context.model_scope', [
            'model' => ApprovableModelLabel::resolve($this->getContextualApprovableType()),
        ]);
    }

    protected function getHeaderActions(): array
    {
        if (! $this->hasContextualScope()) {
            return [];
        }

        return [
            Action::make('clearContext')
                ->label(__('filament-action-approvals::approval.actions.clear_context'))
                ->icon(Heroicon::OutlinedXMark)
                ->color('gray')
                ->url(ApprovalResource::getUrl('index')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        if (! FilamentActionApprovalsPlugin::shouldShowResourceWidgets()) {
            return [];
        }

        return [
            ApprovalAnalyticsWidget::class,
        ];
    }

    /**
     * @return Builder<Approval>
     */
    protected function getTableQuery(): Builder
    {
        return $this->applyContextualScope(
            Approval::query()->withOperationalRelations(),
        );
    }

    protected function getStatusCount(ApprovalStatus $status): int
    {
        return $this->applyContextualScope(Approval::query())
            ->where('status', $status->value)
            ->count();
    }

    protected function getAllCount(): int
    {
        return $this->applyContextualScope(Approval::query())->count();
    }

    /**
     * @param  Builder<Approval>  $query
     * @return Builder<Approval>
     */
    protected function applyContextualScope(Builder $query): Builder
    {
        $approvableType = $this->getContextualApprovableType();
        $approvableId = $this->getContextualApprovableId();

        return $query
            ->when(
                filled($approvableType),
                fn (Builder $builder): Builder => $builder->where('approvable_type', $approvableType),
            )
            ->when(
                filled($approvableId),
                fn (Builder $builder): Builder => $builder->where('approvable_id', $approvableId),
            );
    }

    protected function hasContextualScope(): bool
    {
        return filled($this->getContextualApprovableType()) || filled($this->getContextualApprovableId());
    }

    protected function getContextualApprovableType(): ?string
    {
        $approvableType = request()->query('approvableType');

        return is_string($approvableType) && filled($approvableType)
            ? $approvableType
            : null;
    }

    protected function getContextualApprovableId(): ?string
    {
        $approvableId = request()->query('approvableId');

        if (is_array($approvableId) || $approvableId === null) {
            return null;
        }

        $approvableId = (string) $approvableId;

        return filled($approvableId)
            ? $approvableId
            : null;
    }
}
