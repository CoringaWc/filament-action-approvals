<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Attributes;

use Attribute;
use BackedEnum;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalOperation;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class ApprovableUpdate extends ApprovableOperation
{
    /**
     * @param  list<string>  $fields
     * @param  array<string, array<string, mixed>|list<string>>  $relationships
     */
    public function __construct(
        string|BackedEnum|null $action = null,
        array $fields = [],
        array $relationships = [],
        bool $enabled = true,
    ) {
        parent::__construct(
            operation: ApprovalOperation::Update,
            fields: $fields,
            enabled: $enabled,
            action: $action,
            relationships: $relationships,
        );
    }
}
