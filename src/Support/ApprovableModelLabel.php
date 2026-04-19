<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class ApprovableModelLabel
{
    public static function resolve(string|Model|null $model): string
    {
        if ($model === null) {
            return __('filament-action-approvals::approval.flow_table.any');
        }

        $requestedModel = $model instanceof Model ? $model->getMorphClass() : $model;
        $modelClass = static::normalizeModelClass($model);

        if ($modelClass === null) {
            return Str::headline(class_basename($requestedModel));
        }

        foreach (Filament::getPanels() as $panel) {
            foreach ($panel->getResources() as $resource) {
                $resourceModel = $resource::getModel();

                if (($resourceModel === $modelClass) || (static::getMorphClass($resourceModel) === $requestedModel)) {
                    return $resource::getModelLabel();
                }
            }
        }

        return Str::headline(class_basename($modelClass));
    }

    public static function resolveWithKey(string|Model|null $model, int|string|null $key): string
    {
        $label = static::resolve($model);

        if ($key === null) {
            return $label;
        }

        return $label.' #'.$key;
    }

    protected static function normalizeModelClass(string|Model $model): ?string
    {
        if ($model instanceof Model) {
            return $model::class;
        }

        $morphedModel = Relation::getMorphedModel($model);

        if (is_string($morphedModel) && class_exists($morphedModel)) {
            return $morphedModel;
        }

        return class_exists($model) ? $model : null;
    }

    protected static function getMorphClass(string $modelClass): ?string
    {
        if (! class_exists($modelClass)) {
            return null;
        }

        return app($modelClass)->getMorphClass();
    }
}
