<?php

namespace CoringaWc\FilamentActionApprovals\Concerns;

use CoringaWc\FilamentActionApprovals\Actions\ApproveAction;
use CoringaWc\FilamentActionApprovals\Actions\CommentAction;
use CoringaWc\FilamentActionApprovals\Actions\DelegateAction;
use CoringaWc\FilamentActionApprovals\Actions\RejectAction;
use CoringaWc\FilamentActionApprovals\Actions\SubmitForApprovalAction;
use Filament\Actions\Action;

trait HasApprovalsResource
{
    /**
     * @return array<Action>
     */
    public static function getApprovalHeaderActions(): array
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
