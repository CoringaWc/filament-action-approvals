<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Contracts\Support\Htmlable;
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
        if ($model instanceof Model) {
            return static::resolveRecord($model);
        }

        $label = static::resolve($model);

        if ($key === null) {
            return $label;
        }

        $record = static::resolveRecordByKey($model, $key);

        if ($record instanceof Model) {
            return static::resolveRecord($record);
        }

        return $label.' #'.$key;
    }

    public static function resolveRecord(Model $record): string
    {
        $modelLabel = static::resolve($record);
        $recordLabel = static::resolveRecordLabel($record);

        return filled($recordLabel)
            ? $modelLabel.': '.$recordLabel
            : $modelLabel.' #'.$record->getKey();
    }

    protected static function resolveRecordLabel(Model $record): ?string
    {
        if (method_exists($record, 'getApprovalRecordLabel')) {
            /** @var mixed $customLabel */
            $customLabel = $record->getApprovalRecordLabel();
            $customLabel = static::cleanText($customLabel);

            if ($customLabel !== null) {
                return $customLabel;
            }
        }

        $resource = static::resourceForModel($record);

        if ($resource === null || ! $resource::hasRecordTitle()) {
            return null;
        }

        return static::cleanText($resource::getRecordTitle($record));
    }

    protected static function cleanText(mixed $value): ?string
    {
        if ($value instanceof Htmlable) {
            $value = strip_tags($value->toHtml());
        }

        if (! is_scalar($value)) {
            return null;
        }

        $value = Str::of((string) $value)->trim()->toString();

        return filled($value) ? $value : null;
    }

    protected static function resolveRecordByKey(?string $model, int|string $key): ?Model
    {
        if ($model === null) {
            return null;
        }

        $modelClass = static::normalizeModelClass($model);

        if ($modelClass === null) {
            return null;
        }

        return $modelClass::query()->whereKey($key)->first();
    }

    /**
     * @return class-string<\Filament\Resources\Resource>|null
     */
    protected static function resourceForModel(Model $record): ?string
    {
        $requestedModel = $record->getMorphClass();
        $modelClass = $record::class;

        foreach (Filament::getPanels() as $panel) {
            foreach ($panel->getResources() as $resource) {
                if (! is_a($resource, Resource::class, true)) {
                    continue;
                }

                $resourceModel = $resource::getModel();

                if (($resourceModel === $modelClass) || (static::getMorphClass($resourceModel) === $requestedModel)) {
                    return $resource;
                }
            }
        }

        return null;
    }

    /**
     * @return class-string<Model>|null
     */
    protected static function normalizeModelClass(string|Model $model): ?string
    {
        if ($model instanceof Model) {
            return $model::class;
        }

        $morphedModel = Relation::getMorphedModel($model);

        if (is_string($morphedModel)) {
            return $morphedModel;
        }

        return is_a($model, Model::class, true) ? $model : null;
    }

    protected static function getMorphClass(string $modelClass): ?string
    {
        if (! class_exists($modelClass)) {
            return null;
        }

        return app($modelClass)->getMorphClass();
    }
}
