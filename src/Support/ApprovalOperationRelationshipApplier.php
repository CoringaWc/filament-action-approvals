<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final class ApprovalOperationRelationshipApplier
{
    /**
     * @param  array<string, mixed>  $relationships
     */
    public function apply(Model $record, array $relationships): void
    {
        foreach ($relationships as $relationshipName => $payload) {
            if (blank($relationshipName) || ! is_array($payload) || ! method_exists($record, $relationshipName)) {
                $this->fail();
            }

            $relationship = $record->{$relationshipName}();

            if ($relationship instanceof HasOne || $relationship instanceof MorphOne) {
                $this->applySingularRelationship($relationship, $payload);

                continue;
            }

            if ($relationship instanceof HasMany || $relationship instanceof MorphMany) {
                $this->applyManyRelationship($relationship, $payload);

                continue;
            }

            $this->fail();
        }
    }

    /**
     * @param  HasOne<Model, Model>|MorphOne<Model, Model>  $relationship
     * @param  array<string, mixed>  $payload
     */
    private function applySingularRelationship(HasOne|MorphOne $relationship, array $payload): void
    {
        $operation = Arr::get($payload, 'operation');

        if (! in_array($operation, ['create', 'update', 'delete'], true)) {
            $this->fail();
        }

        $attributes = Arr::get($payload, 'attributes', []);

        if ($operation !== 'delete' && ! is_array($attributes)) {
            $this->fail();
        }

        if ($operation === 'create') {
            $relationship->create($attributes);

            return;
        }

        $related = $this->relatedRecord($relationship, Arr::get($payload, 'record_key'));

        if (! $related instanceof Model) {
            $this->fail();
        }

        if ($operation === 'delete') {
            $related->delete();

            return;
        }

        $related->fill($attributes);
        $related->save();
    }

    /**
     * @param  HasMany<Model, Model>|MorphMany<Model, Model>  $relationship
     * @param  array<string, mixed>  $payload
     */
    private function applyManyRelationship(HasMany|MorphMany $relationship, array $payload): void
    {
        $operations = Arr::get($payload, 'operations', []);

        if (! is_array($operations)) {
            $this->fail();
        }

        foreach ($operations as $operationPayload) {
            if (! is_array($operationPayload)) {
                $this->fail();
            }

            $operation = Arr::get($operationPayload, 'operation');

            if ($operation === 'create') {
                $attributes = Arr::get($operationPayload, 'attributes', []);

                if (! is_array($attributes)) {
                    $this->fail();
                }

                $relationship->create($attributes);

                continue;
            }

            if ($operation === 'reorder') {
                continue;
            }

            if (! in_array($operation, ['update', 'delete'], true)) {
                $this->fail();
            }

            $related = $this->relatedRecord($relationship, Arr::get($operationPayload, 'record_key'));

            if (! $related instanceof Model) {
                $this->fail();
            }

            if ($operation === 'delete') {
                $related->delete();

                continue;
            }

            $attributes = Arr::get($operationPayload, 'attributes', []);

            if (! is_array($attributes)) {
                $this->fail();
            }

            $related->fill($attributes);
            $related->save();
        }
    }

    /**
     * @param  HasOne<Model, Model>|MorphOne<Model, Model>|HasMany<Model, Model>|MorphMany<Model, Model>  $relationship
     */
    private function relatedRecord(HasOne|MorphOne|HasMany|MorphMany $relationship, mixed $recordKey): ?Model
    {
        if (! is_int($recordKey) && ! is_string($recordKey)) {
            return null;
        }

        $related = (clone $relationship)
            ->getQuery()
            ->whereKey($recordKey)
            ->lockForUpdate()
            ->first();

        return $related instanceof Model ? $related : null;
    }

    private function fail(): never
    {
        throw ValidationException::withMessages([
            'approval' => __('filament-action-approvals::approval.actions.apply_failed'),
        ]);
    }
}
