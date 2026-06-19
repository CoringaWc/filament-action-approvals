<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

final class ApprovalActionKey
{
    public static function raw(string|BackedEnum|null $action): ?string
    {
        if ($action instanceof BackedEnum) {
            return (string) $action->value;
        }

        return filled($action) ? (string) $action : null;
    }

    public static function normalize(Model|string|null $model, string|BackedEnum|null $action): ?string
    {
        $rawAction = self::raw($action);

        if ($rawAction === null) {
            return null;
        }

        $namespace = self::namespaceFor($model);

        if ($namespace === null || $rawAction === $namespace || Str::startsWith($rawAction, $namespace.'.')) {
            return $rawAction;
        }

        return $namespace.'.'.$rawAction;
    }

    public static function local(Model|string|null $model, ?string $actionKey): ?string
    {
        if (! filled($actionKey)) {
            return null;
        }

        $namespace = self::namespaceFor($model);

        if ($namespace !== null && Str::startsWith($actionKey, $namespace.'.')) {
            return Str::after($actionKey, $namespace.'.');
        }

        return $actionKey;
    }

    public static function namespaceFor(Model|string|null $model): ?string
    {
        $modelInstance = self::modelInstance($model);
        $modelClass = $modelInstance instanceof Model ? $modelInstance::class : self::modelClass($model);

        if ($modelInstance instanceof Model && method_exists($modelInstance, 'approvalActionNamespace')) {
            $namespace = $modelInstance->approvalActionNamespace();

            if (is_string($namespace) && filled($namespace)) {
                return trim($namespace, '.');
            }
        }

        if ($modelInstance instanceof Model) {
            $morphClass = $modelInstance->getMorphClass();

            if (filled($morphClass) && $morphClass !== $modelInstance::class && ! str_contains($morphClass, '\\')) {
                return trim($morphClass, '.');
            }
        }

        if ($modelClass !== null) {
            return Str::kebab(class_basename($modelClass));
        }

        return null;
    }

    /**
     * @return class-string<Model>|null
     */
    private static function modelClass(Model|string|null $model): ?string
    {
        if ($model instanceof Model) {
            return $model::class;
        }

        if (! is_string($model) || blank($model)) {
            return null;
        }

        $morphedModel = Relation::getMorphedModel($model);

        if (is_string($morphedModel)) {
            return $morphedModel;
        }

        return class_exists($model) && is_subclass_of($model, Model::class) ? $model : null;
    }

    private static function modelInstance(Model|string|null $model): ?Model
    {
        if ($model instanceof Model) {
            return $model;
        }

        $modelClass = self::modelClass($model);

        return $modelClass === null ? null : new $modelClass;
    }
}
