<?php

namespace CoringaWc\FilamentActionApprovals\Resources\ApprovalFlowResource\Pages;

use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlowResource;
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
