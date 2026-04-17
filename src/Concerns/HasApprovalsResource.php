<?php

namespace CoringaWc\FilamentActionApprovals\Concerns;

use Filament\Actions\Action;
use CoringaWc\FilamentActionApprovals\Actions\ApproveAction;
use CoringaWc\FilamentActionApprovals\Actions\CommentAction;
use CoringaWc\FilamentActionApprovals\Actions\DelegateAction;
use CoringaWc\FilamentActionApprovals\Actions\RejectAction;
use CoringaWc\FilamentActionApprovals\Actions\SubmitForApprovalAction;

trait HasApprovalsResource
{
    /**
     * @return array<Action>
     */
    protected function getApprovalHeaderActions(): array
    {
        return [
            SubmitForApprovalAction::make(),
            ApproveAction::make(),
            RejectAction::make(),
            CommentAction::make(),
            DelegateAction::make(),
        ];
    }
}
