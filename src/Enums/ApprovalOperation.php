<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Enums;

use Filament\Support\Contracts\HasLabel;

enum ApprovalOperation: string implements HasLabel
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Restore = 'restore';
    case ForceDelete = 'force_delete';

    public function getLabel(): string
    {
        return match ($this) {
            self::Create => __('filament-action-approvals::approval.operation.create'),
            self::Update => __('filament-action-approvals::approval.operation.update'),
            self::Delete => __('filament-action-approvals::approval.operation.delete'),
            self::Restore => __('filament-action-approvals::approval.operation.restore'),
            self::ForceDelete => __('filament-action-approvals::approval.operation.force_delete'),
        };
    }

    public static function fromOperation(self|string $operation): ?self
    {
        if ($operation instanceof self) {
            return $operation;
        }

        return match ($operation) {
            'edit' => self::Update,
            default => self::tryFrom($operation),
        };
    }

    public static function normalize(self|string $operation): string
    {
        $normalizedOperation = self::fromOperation($operation);

        if ($normalizedOperation instanceof self) {
            return $normalizedOperation->value;
        }

        return is_string($operation) ? $operation : $operation->value;
    }

    public function matches(self|string $operation): bool
    {
        return self::fromOperation($operation) === $this;
    }
}
