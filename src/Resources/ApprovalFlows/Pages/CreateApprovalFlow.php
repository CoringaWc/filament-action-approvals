<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Resources\ApprovalFlows\Pages;

use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlows\ApprovalFlowResource;
use Filament\Resources\Pages\CreateRecord;

class CreateApprovalFlow extends CreateRecord
{
    protected static string $resource = ApprovalFlowResource::class;
}
