<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Resources\ApprovalFlowResource\Pages;

use CoringaWc\FilamentActionApprovals\Enums\StepInstanceStatus;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlowResource;
use CoringaWc\FilamentActionApprovals\Widgets\ApprovalAnalyticsWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListApprovalFlows extends ListRecords
{
    protected static string $resource = ApprovalFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
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
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('filament-action-approvals::approval.tabs.all')),
            'active' => Tab::make(__('filament-action-approvals::approval.tabs.active'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_active', true)),
            'inactive' => Tab::make(__('filament-action-approvals::approval.tabs.inactive'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_active', false)),
            'pending' => Tab::make(__('filament-action-approvals::approval.tabs.pending'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereHas(
                    'approvals.stepInstances',
                    fn (Builder $q): Builder => $q
                        ->where('status', StepInstanceStatus::Waiting)
                        ->whereJsonContains('assigned_approver_ids', auth()->id()),
                )),
        ];
    }
}
