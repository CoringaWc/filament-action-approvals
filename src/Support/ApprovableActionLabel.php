<?php

namespace CoringaWc\FilamentActionApprovals\Support;

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
