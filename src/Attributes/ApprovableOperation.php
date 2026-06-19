<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Attributes;

use Attribute;
use BackedEnum;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalOperation;
use CoringaWc\FilamentActionApprovals\Support\ApprovalActionKey;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class ApprovableOperation
{
    /**
     * @param  list<string>  $fields
     * @param  array<string, array<string, mixed>|list<string>>  $relationships
     */
    public function __construct(
        public ApprovalOperation|string|null $operation = null,
        public string|BackedEnum|null $actionKey = null,
        public array $fields = [],
        public bool $enabled = true,
        public string|BackedEnum|null $action = null,
        public array $relationships = [],
    ) {}

    public function normalizedOperation(ApprovalOperation|string|null $fallback = null): ?string
    {
        if ($this->operation === null) {
            return $fallback === null ? null : ApprovalOperation::normalize($fallback);
        }

        return ApprovalOperation::normalize($this->operation);
    }

    public function matchesOperation(ApprovalOperation|string $operation): bool
    {
        $normalizedOperation = ApprovalOperation::normalize($operation);

        if ($this->operation !== null) {
            return $this->normalizedOperation() === $normalizedOperation;
        }

        return $normalizedOperation === ApprovalOperation::Update->value
            && ($this->fields !== [] || $this->relationships !== []);
    }

    public function normalizedActionKey(Model|string|null $model = null): string
    {
        $normalizedAction = ApprovalActionKey::normalize($model, $this->action);
        $legacyActionKey = ApprovalActionKey::raw($this->actionKey);

        if ($normalizedAction !== null && $legacyActionKey !== null) {
            $normalizedLegacyActionKey = ApprovalActionKey::normalize($model, $legacyActionKey);

            if ($normalizedLegacyActionKey !== $normalizedAction) {
                throw new InvalidArgumentException('The ApprovableOperation action and actionKey values do not normalize to the same approval action.');
            }

            return $normalizedAction;
        }

        if ($normalizedAction !== null) {
            return $normalizedAction;
        }

        if ($legacyActionKey !== null) {
            return $legacyActionKey;
        }

        throw new InvalidArgumentException('An ApprovableOperation action or actionKey value is required.');
    }

    public function localActionKey(): string
    {
        $action = ApprovalActionKey::raw($this->action ?? $this->actionKey);

        if ($action !== null) {
            return $action;
        }

        throw new InvalidArgumentException('An ApprovableOperation action or actionKey value is required.');
    }
}
