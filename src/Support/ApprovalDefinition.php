<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use BackedEnum;
use CoringaWc\FilamentActionApprovals\Attributes\ApprovableOperation;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalOperation;

class ApprovalDefinition extends ApprovableOperation
{
    /**
     * @param  list<string>  $fields
     * @param  array<string, array<string, mixed>|list<string>>  $relationships
     * @param  array<string, array<string, mixed>|list<string>>  $directPayload
     */
    public function __construct(
        ?ApprovalOperation $operation = null,
        array $fields = [],
        array $relationships = [],
        bool $enabled = true,
        string|BackedEnum|null $action = null,
        array $directPayload = [],
    ) {
        parent::__construct(
            operation: $operation,
            fields: $fields,
            enabled: $enabled,
            action: $action,
            relationships: $relationships,
            directPayload: $directPayload,
        );
    }

    /**
     * @param  list<string>  $fields
     * @param  array<string, array<string, mixed>|list<string>>  $relationships
     */
    public static function update(array $fields = [], array $relationships = []): self
    {
        return new self(
            operation: ApprovalOperation::Update,
            fields: $fields,
            relationships: $relationships,
        );
    }

    public static function delete(): self
    {
        return new self(operation: ApprovalOperation::Delete);
    }

    public static function restore(): self
    {
        return new self(operation: ApprovalOperation::Restore);
    }

    public static function forceDelete(): self
    {
        return new self(operation: ApprovalOperation::ForceDelete);
    }

    public static function manual(): self
    {
        return new self;
    }

    public function forAction(string|BackedEnum $action): self
    {
        $definition = clone $this;
        $definition->action = $action;

        return $definition;
    }

    /**
     * @param  array<string, array<string, mixed>|list<string>>  $directPayload
     */
    public function withDirectPayload(array $directPayload): self
    {
        $definition = clone $this;
        $definition->directPayload = $directPayload;

        return $definition;
    }
}
