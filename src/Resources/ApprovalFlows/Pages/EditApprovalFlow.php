<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Resources\ApprovalFlows\Pages;

use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlows\ApprovalFlowResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditApprovalFlow extends EditRecord
{
    protected static string $resource = ApprovalFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
