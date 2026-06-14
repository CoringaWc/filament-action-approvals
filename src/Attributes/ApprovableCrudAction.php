<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Attributes;

use Attribute;
use BackedEnum;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class ApprovableCrudAction
{
    /**
     * @param  list<string>  $fields
     */
    public function __construct(
        public string $operation,
        public string|BackedEnum $actionKey,
        public array $fields = [],
        public bool $enabled = true,
    ) {}

    public function normalizedActionKey(): string
    {
        return $this->actionKey instanceof BackedEnum
            ? (string) $this->actionKey->value
            : $this->actionKey;
    }
}
