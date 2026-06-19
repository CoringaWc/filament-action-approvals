<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Attributes;

use Attribute;
use BackedEnum;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalOperation;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class ApprovableForceDelete extends ApprovableOperation
{
    public function __construct(
        string|BackedEnum|null $action = null,
        bool $enabled = true,
    ) {
        parent::__construct(
            operation: ApprovalOperation::ForceDelete,
            enabled: $enabled,
            action: $action,
        );
    }
}
