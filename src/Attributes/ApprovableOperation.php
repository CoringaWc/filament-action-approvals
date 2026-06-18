<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Attributes;

use Attribute;
use BackedEnum;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalOperation;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class ApprovableOperation
{
    /**
     * @param  list<string>  $fields
     */
    public function __construct(
        public ApprovalOperation|string $operation,
        public string|BackedEnum $actionKey,
        public array $fields = [],
        public bool $enabled = true,
    ) {}

    public function normalizedOperation(): string
    {
        return ApprovalOperation::normalize($this->operation);
    }

    public function normalizedActionKey(): string
    {
        return $this->actionKey instanceof BackedEnum
            ? (string) $this->actionKey->value
            : $this->actionKey;
    }
}
