<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

final class UserModelKey
{
    public static function modelClass(): string
    {
        return FilamentActionApprovalsPlugin::resolveUserModel();
    }

    public static function tableName(): string
    {
        $configuredTable = config('filament-action-approvals.user_table');

        if (is_string($configuredTable) && filled($configuredTable)) {
            return $configuredTable;
        }

        /** @var Model $model */
        $model = app(self::modelClass());

        return $model->getTable();
    }

    public static function keyName(): string
    {
        /** @var Model $model */
        $model = app(self::modelClass());

        return $model->getKeyName();
    }

    public static function keyType(): string
    {
        $configuredType = config('filament-action-approvals.user_key_type');

        if (is_string($configuredType) && in_array($configuredType, ['integer', 'uuid', 'ulid', 'string'], true)) {
            return $configuredType;
        }

        /** @var Model $model */
        $model = app(self::modelClass());

        if ($model->getKeyType() === 'int' || $model->getKeyType() === 'integer') {
            return 'integer';
        }

        $uniqueId = (string) $model->newUniqueId();

        if (Str::isUlid($uniqueId)) {
            return 'ulid';
        }

        if (Str::isUuid($uniqueId)) {
            return 'uuid';
        }

        return 'string';
    }

    public static function addColumn(Blueprint $table, string $column, bool $nullable = false): void
    {
        $definition = match (self::keyType()) {
            'uuid' => $table->uuid($column),
            'ulid' => $table->ulid($column),
            'string' => $table->string($column, (int) config('filament-action-approvals.user_key_length', 255)),
            default => $table->unsignedBigInteger($column),
        };

        if ($nullable) {
            $definition->nullable();
        }
    }

    public static function normalize(mixed $userId): int|string|null
    {
        if (! is_int($userId) && ! is_string($userId)) {
            return null;
        }

        if (self::keyType() === 'integer') {
            return is_string($userId) && ctype_digit($userId) ? (int) $userId : $userId;
        }

        return (string) $userId;
    }

    public static function isConfiguredUserModel(Model $user): bool
    {
        return is_a($user, self::modelClass());
    }

    public static function configuredUserMorphClass(): string
    {
        /** @var Model $model */
        $model = app(self::modelClass());

        return $model->getMorphClass();
    }
}
