<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Resources\ApprovalFlows\Pages;

use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlows\ApprovalFlowResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListApprovalFlows extends ListRecords
{
    protected static string $resource = ApprovalFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
