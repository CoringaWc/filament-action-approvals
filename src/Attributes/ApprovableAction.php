<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Attributes;

use Attribute;
use BackedEnum;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class ApprovableAction extends ApprovableOperation
{
    public function __construct(
        string|BackedEnum $action,
        bool $enabled = true,
    ) {
        parent::__construct(
            enabled: $enabled,
            action: $action,
        );
    }
}
