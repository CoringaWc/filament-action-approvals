<?php

namespace CoringaWc\FilamentActionApprovals\Resources\ApprovalFlowResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlowResource;

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
