<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Resources\ApprovalFlowResource\Pages;

use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlowResource;
use Filament\Resources\Pages\CreateRecord;

class CreateApprovalFlow extends CreateRecord
{
    protected static string $resource = ApprovalFlowResource::class;
}
