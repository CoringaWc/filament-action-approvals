<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Concerns;

use CoringaWc\FilamentActionApprovals\Support\ApprovalActionGroup;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

trait HasApprovalsResource
{
    /**
     * @return array<Action|ActionGroup>
     */
    public static function getApprovalHeaderActions(): array
    {
        return [
            ApprovalActionGroup::make(),
        ];
    }

    /**
     * @return array<Action|ActionGroup>
     */
    public static function getApprovalResponseHeaderActions(): array
    {
        return [
            ApprovalActionGroup::make(includeSubmit: false),
        ];
    }
}
