<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class ApprovableActionLabel
{
    /**
     * @return array<string, string>
     */
    public static function optionsFor(string|Model|null $model): array
    {
        $modelClass = static::normalizeModelClass($model);

        if ($modelClass === null || ! method_exists($modelClass, 'approvableActions')) {
            return [];
        }

        $actions = $modelClass::approvableActions();

        return is_array($actions) ? $actions : [];
    }

    public static function hasOptionsFor(string|Model|null $model): bool
    {
        return static::optionsFor($model) !== [];
    }

    public static function resolve(string|Model|null $model, ?string $actionKey): string
    {
        if (blank($actionKey)) {
            return __('filament-action-approvals::approval.flow.any_action');
        }

        return static::optionsFor($model)[$actionKey] ?? Str::headline($actionKey);
    }

    /**
     * Get the enum case for a given action key, if the model uses an enum.
     */
    public static function resolveEnum(string|Model|null $model, ?string $actionKey): ?BackedEnum
    {
        if (blank($actionKey)) {
            return null;
        }

        $enumClass = static::enumClassFor($model);

        if ($enumClass === null) {
            return null;
        }

        return $enumClass::tryFrom($actionKey);
    }

    /**
     * Get the enum class-string for the model's approvable actions.
     *
     * @return class-string<BackedEnum>|null
     */
    public static function enumClassFor(string|Model|null $model): ?string
    {
        $modelClass = static::normalizeModelClass($model);

        if ($modelClass === null || ! method_exists($modelClass, 'approvableActionsEnumClass')) {
            return null;
        }

        return $modelClass::approvableActionsEnumClass();
    }

    /**
     * Get the icon for an action key, if the model uses an enum with HasIcon.
     */
    public static function iconFor(string|Model|null $model, ?string $actionKey): ?string
    {
        $case = static::resolveEnum($model, $actionKey);

        if ($case !== null && method_exists($case, 'getIcon')) {
            return $case->getIcon();
        }

        return null;
    }

    /**
     * Get the color for an action key, if the model uses an enum with HasColor.
     *
     * @return string|array<string|int, string|int>|null
     */
    public static function colorFor(string|Model|null $model, ?string $actionKey): string|array|null
    {
        $case = static::resolveEnum($model, $actionKey);

        if ($case !== null && method_exists($case, 'getColor')) {
            return $case->getColor();
        }

        return null;
    }

    protected static function normalizeModelClass(string|Model|null $model): ?string
    {
        if ($model instanceof Model) {
            return $model::class;
        }

        if (! is_string($model) || blank($model)) {
            return null;
        }

        $morphedModel = Relation::getMorphedModel($model);

        if (is_string($morphedModel) && class_exists($morphedModel)) {
            return $morphedModel;
        }

        return class_exists($model) ? $model : null;
    }
}
