<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Actions;

use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Support\ApprovalModels;
use CoringaWc\FilamentActionApprovals\Support\CurrentPanelUser;
use CoringaWc\FilamentActionApprovals\Widgets\RequesterApprovalsTable;
use Illuminate\Database\Eloquent\Builder;

class ListRequesterApprovalsAction extends ListApprovalsAction
{
    public static function getDefaultName(): ?string
    {
        return 'listRequesterApprovals';
    }

    /**
     * @return class-string<RequesterApprovalsTable>
     */
    protected function tableWidget(): string
    {
        return RequesterApprovalsTable::class;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->hideWhenNoActionableApprovals(false)
            ->label(__('filament-action-approvals::approval.actions.list_requester_approvals'))
            ->modalHeading(__('filament-action-approvals::approval.actions.list_requester_approvals'))
            ->visible(fn (): bool => $this->hasConfiguredScope() && $this->requesterHasApprovalHistory());
    }

    protected function hasConfiguredScope(): bool
    {
        return $this->resolveApprovableRecord() !== null || filled($this->resolveApprovableType());
    }

    protected function requesterHasApprovalHistory(): bool
    {
        return $this->requesterHistoryQuery()->exists();
    }

    /**
     * @return Builder<Approval>
     */
    protected function requesterHistoryQuery(): Builder
    {
        return RequesterApprovalsTable::scopeToContext(
            ApprovalModels::approvalQuery()
                ->submittedByRequester(CurrentPanelUser::model()),
            $this->getTableParameters(),
        );
    }
}
