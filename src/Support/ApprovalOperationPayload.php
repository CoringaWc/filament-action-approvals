<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApprovalOperationPayload
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $allowedFields
     * @param  array<string, array<string, mixed>|list<string>>  $relationshipDefinitions
     * @return array<string, mixed>
     */
    public function editPayload(Model $record, array $data, array $allowedFields, array $relationshipDefinitions = []): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->editPayloadData($record, $data, $allowedFields, $relationshipDefinitions)['payload'];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $allowedFields
     * @param  array<string, array<string, mixed>|list<string>>  $relationshipDefinitions
     * @return list<string>
     */
    public function editFields(Model $record, array $data, array $allowedFields, array $relationshipDefinitions = []): array
    {
        /** @var list<string> $fields */
        $fields = $this->editPayloadData($record, $data, $allowedFields, $relationshipDefinitions)['fields'];

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $allowedFields
     * @param  array<string, array<string, mixed>|list<string>>  $relationshipDefinitions
     * @return list<array<string, mixed>>
     */
    public function editDiff(Model $record, array $data, array $allowedFields, array $relationshipDefinitions = []): array
    {
        /** @var list<array<string, mixed>> $diff */
        $diff = $this->editPayloadData($record, $data, $allowedFields, $relationshipDefinitions)['diff'];

        return $diff;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $allowedFields
     * @param  array<string, array<string, mixed>|list<string>>  $relationshipDefinitions
     * @return array{payload: array<string, mixed>, fields: list<string>, diff: list<array<string, mixed>>, relationships: array<string, array{type: string|null, fields: list<string>}>}
     */
    public function editPayloadData(Model $record, array $data, array $allowedFields, array $relationshipDefinitions = []): array
    {
        $payload = [];
        $diff = [];
        $changedFields = [];

        foreach ($allowedFields as $field) {
            if (blank($field) || $this->isDeniedField($record, $field) || ! Arr::has($data, $field)) {
                continue;
            }

            $requested = Arr::get($data, $field);

            if ($this->isDeniedValue($requested)) {
                continue;
            }

            $current = data_get($record, $field);

            if ($this->valuesMatch($current, $requested)) {
                continue;
            }

            $payload[$field] = $this->sanitizeValue($requested);
            $changedFields[] = $field;
            $diff[] = [
                'field' => $field,
                'current' => $this->sanitizeValue($current),
                'requested' => $this->sanitizeValue($requested),
            ];
        }

        $relationshipData = $this->relationshipPayloadData($record, $data, $relationshipDefinitions);

        if ($relationshipData['payload'] !== []) {
            $payload['relationships'] = $relationshipData['payload'];
            $diff = [
                ...$diff,
                ...$relationshipData['diff'],
            ];
        }

        return [
            'payload' => $payload,
            'fields' => $changedFields,
            'diff' => $diff,
            'relationships' => $relationshipData['definitions'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, mixed>|list<string>>  $directDefinitions
     * @return array{payload: array<string, mixed>, ignored: list<string>}
     */
    public function directPayloadData(Model $record, array $data, array $directDefinitions): array
    {
        $payload = [];
        $ignored = [];

        foreach ($directDefinitions as $relationshipName => $definition) {
            if (blank($relationshipName) || ! method_exists($record, $relationshipName) || ! Arr::has($data, $relationshipName)) {
                continue;
            }

            $normalizedDefinition = $this->normalizeDirectDefinition($definition);

            if ($normalizedDefinition['fields'] === []) {
                continue;
            }

            $state = Arr::get($data, $relationshipName);

            if (! is_array($state)) {
                continue;
            }

            $directRelationship = $this->directRelationshipPayload(
                $record,
                $relationshipName,
                $state,
                $normalizedDefinition,
            );

            if ($directRelationship['payload'] === []) {
                continue;
            }

            $payload[$relationshipName] = $directRelationship['payload'];
            $ignored = [
                ...$ignored,
                ...$directRelationship['ignored'],
            ];
        }

        return [
            'payload' => $payload,
            'ignored' => array_values(array_unique($ignored)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deletePayload(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, mixed>|list<string>>  $relationshipDefinitions
     * @return array{payload: array<string, mixed>, diff: list<array<string, mixed>>, definitions: array<string, array{type: string|null, fields: list<string>}>}
     */
    private function relationshipPayloadData(Model $record, array $data, array $relationshipDefinitions): array
    {
        $payload = [];
        $diff = [];
        $changedDefinitions = [];

        foreach ($relationshipDefinitions as $relationshipName => $definition) {
            if (blank($relationshipName) || ! method_exists($record, $relationshipName)) {
                continue;
            }

            $normalizedDefinition = $this->normalizeRelationshipDefinition($definition);

            if ($normalizedDefinition['fields'] === []) {
                continue;
            }

            $relationshipPayload = $this->isManyRelationshipState($data, $relationshipName, $normalizedDefinition)
                ? $this->manyRelationshipPayload($record, $data, $relationshipName, $normalizedDefinition)
                : $this->singularRelationshipPayload($record, $data, $relationshipName, $normalizedDefinition);

            if ($relationshipPayload['payload'] === []) {
                continue;
            }

            $payload[$relationshipName] = $relationshipPayload['payload'];
            $diff = [
                ...$diff,
                ...$relationshipPayload['diff'],
            ];
            $changedDefinitions[$relationshipName] = [
                'type' => $normalizedDefinition['type'],
                'fields' => $normalizedDefinition['fields'],
            ];
        }

        return [
            'payload' => $payload,
            'diff' => $diff,
            'definitions' => $changedDefinitions,
        ];
    }

    /**
     * @param  array<string, mixed>|list<string>  $definition
     * @return array{type: string|null, fields: list<string>}
     */
    private function normalizeRelationshipDefinition(array $definition): array
    {
        $fields = array_is_list($definition)
            ? $definition
            : ($definition['fields'] ?? []);

        return [
            'type' => is_string($definition['type'] ?? null) ? $definition['type'] : null,
            'fields' => array_values(collect(is_array($fields) ? $fields : [])
                ->filter(fn (mixed $field): bool => is_string($field) && filled($field))
                ->values()
                ->all()),
        ];
    }

    /**
     * @param  array<string, mixed>|list<string>  $definition
     * @return array{type: string|null, fields: list<string>, operations: list<string>}
     */
    private function normalizeDirectDefinition(array $definition): array
    {
        $fields = array_is_list($definition)
            ? $definition
            : ($definition['fields'] ?? []);

        $operations = array_is_list($definition)
            ? ['replace']
            : ($definition['operations'] ?? ['replace']);

        return [
            'type' => is_string($definition['type'] ?? null) ? $definition['type'] : null,
            'fields' => array_values(collect(is_array($fields) ? $fields : [])
                ->filter(fn (mixed $field): bool => is_string($field) && filled($field))
                ->values()
                ->all()),
            'operations' => array_values(collect(is_array($operations) ? $operations : [])
                ->filter(fn (mixed $operation): bool => is_string($operation) && filled($operation))
                ->values()
                ->all()),
        ];
    }

    /**
     * @param  array<mixed>  $state
     * @param  array{type: string|null, fields: list<string>, operations: list<string>}  $definition
     * @return array{payload: array<string, mixed>, ignored: list<string>}
     */
    private function directRelationshipPayload(Model $record, string $relationshipName, array $state, array $definition): array
    {
        $currentRecords = $this->manyRelatedRecords($record, $relationshipName);
        $rows = [];
        $ignored = [];
        $allowedFields = array_flip($definition['fields']);

        foreach ($state as $clientKey => $row) {
            if (! is_array($row)) {
                continue;
            }

            $recordKey = $this->relationshipRowRecordKey($clientKey, $row);

            if ($recordKey !== null && ! $currentRecords->has((string) $recordKey)) {
                throw ValidationException::withMessages([
                    'approval' => __('filament-action-approvals::approval.actions.apply_failed'),
                ]);
            }

            $attributes = [];

            foreach ($row as $field => $value) {
                if (! is_string($field)) {
                    continue;
                }

                if (! array_key_exists($field, $allowedFields)) {
                    if ($this->isDeniedDirectField($field, $value)) {
                        throw ValidationException::withMessages([
                            'approval' => __('filament-action-approvals::approval.actions.apply_failed'),
                        ]);
                    }

                    $ignored[] = "{$relationshipName}.{$field}";

                    continue;
                }

                if ($this->isClientControlledForeignKey($field) || $this->isDeniedDirectField($field, $value)) {
                    throw ValidationException::withMessages([
                        'approval' => __('filament-action-approvals::approval.actions.apply_failed'),
                    ]);
                }

                if ($field === 'id' || $field === 'record_key') {
                    continue;
                }

                $attributes[$field] = $this->sanitizeValue($value);
            }

            if ($recordKey !== null) {
                $attributes['record_key'] = $recordKey;
            }

            if ($attributes === []) {
                continue;
            }

            $rows[] = $attributes;
        }

        if ($rows === []) {
            return ['payload' => [], 'ignored' => $ignored];
        }

        return [
            'payload' => [
                'operation' => in_array('replace', $definition['operations'], true) ? 'replace' : $definition['operations'][0],
                'records' => $rows,
            ],
            'ignored' => $ignored,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{type: string|null, fields: list<string>}  $definition
     */
    private function isManyRelationshipState(array $data, string $relationshipName, array $definition): bool
    {
        $type = $definition['type'];

        if (is_string($type) && Str::contains(Str::lower($type), ['many', 'repeater'])) {
            return true;
        }

        $state = Arr::get($data, $relationshipName);

        return is_array($state) && collect($state)->contains(fn (mixed $row): bool => is_array($row));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{type: string|null, fields: list<string>}  $definition
     * @return array{payload: array<string, mixed>, diff: list<array<string, mixed>>}
     */
    private function singularRelationshipPayload(Model $record, array $data, string $relationshipName, array $definition): array
    {
        $related = $this->singularRelatedRecord($record, $relationshipName);
        $attributes = [];
        $diff = [];

        foreach ($definition['fields'] as $field) {
            if ($this->isDeniedField($related ?? $record, $field) || ! $this->hasRelationshipFieldState($data, $relationshipName, $field)) {
                continue;
            }

            $requested = $this->getRelationshipFieldState($data, $relationshipName, $field);

            if ($this->isDeniedValue($requested)) {
                continue;
            }

            if (! $related instanceof Model && blank($requested)) {
                continue;
            }

            $current = $related?->getAttribute($field);

            if ($this->valuesMatch($current, $requested)) {
                continue;
            }

            $attributes[$field] = $this->sanitizeValue($requested);
            $diff[] = [
                'relationship' => $relationshipName,
                'field' => $field,
                'current' => $this->sanitizeValue($current),
                'requested' => $this->sanitizeValue($requested),
            ];
        }

        if ($attributes === []) {
            return ['payload' => [], 'diff' => []];
        }

        return [
            'payload' => array_filter([
                'operation' => $related instanceof Model ? 'update' : 'create',
                'record_key' => $related?->getKey(),
                'base_updated_at' => $this->baseUpdatedAt($related),
                'attributes' => $attributes,
            ], static fn (mixed $value): bool => $value !== null),
            'diff' => $diff,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{type: string|null, fields: list<string>}  $definition
     * @return array{payload: array<string, mixed>, diff: list<array<string, mixed>>}
     */
    private function manyRelationshipPayload(Model $record, array $data, string $relationshipName, array $definition): array
    {
        $state = Arr::get($data, $relationshipName);

        if (! is_array($state)) {
            return ['payload' => [], 'diff' => []];
        }

        $currentRecords = $this->manyRelatedRecords($record, $relationshipName);
        $currentKeys = $currentRecords->keys()->map(fn (mixed $key): string => (string) $key)->values()->all();
        $submittedExistingKeys = [];
        $rows = [];
        $diff = [];

        foreach ($state as $clientKey => $row) {
            if (! is_array($row)) {
                continue;
            }

            $recordKey = $this->relationshipRowRecordKey($clientKey, $row);
            $related = $recordKey === null ? null : $currentRecords->get((string) $recordKey);

            if ($recordKey !== null) {
                $submittedExistingKeys[] = (string) $recordKey;
            }

            $attributes = [];

            foreach ($definition['fields'] as $field) {
                if ($this->isDeniedField($related ?? $record, $field) || ! Arr::has($row, $field)) {
                    continue;
                }

                $requested = Arr::get($row, $field);

                if ($this->isDeniedValue($requested)) {
                    continue;
                }

                if (! $related instanceof Model && blank($requested)) {
                    continue;
                }

                $current = $related?->getAttribute($field);

                if ($related instanceof Model && $this->valuesMatch($current, $requested)) {
                    continue;
                }

                $attributes[$field] = $this->sanitizeValue($requested);
                $diff[] = [
                    'relationship' => $relationshipName,
                    'record_key' => $recordKey,
                    'client_key' => is_string($clientKey) ? $clientKey : (string) $clientKey,
                    'field' => $field,
                    'current' => $this->sanitizeValue($current),
                    'requested' => $this->sanitizeValue($requested),
                ];
            }

            $position = is_int($clientKey) ? $clientKey : count($rows);
            $currentPosition = $recordKey === null ? null : array_search((string) $recordKey, $currentKeys, true);
            $positionChanged = is_int($currentPosition) && $currentPosition !== $position;

            if (! $related instanceof Model && ! $this->hasMeaningfulCreateAttributes($attributes)) {
                $attributes = [];
            }

            if ($attributes === [] && ! $positionChanged) {
                continue;
            }

            $rows[] = array_filter([
                'operation' => $related instanceof Model ? ($attributes === [] ? 'reorder' : 'update') : 'create',
                'record_key' => $related?->getKey(),
                'client_key' => $related instanceof Model ? null : (is_string($clientKey) ? $clientKey : (string) $clientKey),
                'base_updated_at' => $this->baseUpdatedAt($related),
                'position' => $position,
                'attributes' => $attributes === [] ? null : $attributes,
            ], static fn (mixed $value): bool => $value !== null);
        }

        foreach (array_diff($currentKeys, $submittedExistingKeys) as $deletedKey) {
            $related = $currentRecords->get($deletedKey);

            $rows[] = array_filter([
                'operation' => 'delete',
                'record_key' => $related?->getKey(),
                'base_updated_at' => $this->baseUpdatedAt($related),
            ], static fn (mixed $value): bool => $value !== null);
        }

        if ($rows === []) {
            return ['payload' => [], 'diff' => []];
        }

        return [
            'payload' => ['operations' => $rows],
            'diff' => $diff,
        ];
    }

    private function singularRelatedRecord(Model $record, string $relationshipName): ?Model
    {
        $related = $record->getRelationValue($relationshipName);

        return $related instanceof Model ? $related : null;
    }

    /**
     * @return Collection<string, Model>
     */
    private function manyRelatedRecords(Model $record, string $relationshipName): Collection
    {
        $related = $record->getRelationValue($relationshipName);

        if ($related instanceof EloquentCollection || $related instanceof Collection) {
            return $related
                ->filter(fn (mixed $item): bool => $item instanceof Model)
                ->keyBy(fn (Model $item): string => (string) $item->getKey());
        }

        $relationship = $record->{$relationshipName}();

        if ($relationship instanceof Relation) {
            return $relationship
                ->get()
                ->keyBy(fn (Model $item): string => (string) $item->getKey());
        }

        return collect();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function relationshipRowRecordKey(int|string $clientKey, array $row): int|string|null
    {
        $rowKey = $row['id'] ?? $row['record_key'] ?? null;

        if (is_int($rowKey) || is_string($rowKey)) {
            return $rowKey;
        }

        if (is_string($clientKey) && Str::startsWith($clientKey, 'record-')) {
            $key = Str::after($clientKey, 'record-');

            return filled($key) ? $key : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasRelationshipFieldState(array $data, string $relationshipName, string $field): bool
    {
        if (Arr::has($data, $relationshipName.'.'.$field)) {
            return true;
        }

        $relationshipState = Arr::get($data, $relationshipName);

        if (is_array($relationshipState) && Arr::has($relationshipState, $field)) {
            return true;
        }

        return Arr::has($data, $field);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function getRelationshipFieldState(array $data, string $relationshipName, string $field): mixed
    {
        if (Arr::has($data, $relationshipName.'.'.$field)) {
            return Arr::get($data, $relationshipName.'.'.$field);
        }

        $relationshipState = Arr::get($data, $relationshipName);

        if (is_array($relationshipState) && Arr::has($relationshipState, $field)) {
            return Arr::get($relationshipState, $field);
        }

        return Arr::get($data, $field);
    }

    private function baseUpdatedAt(?Model $record): ?string
    {
        $updatedAt = $record?->getAttribute('updated_at');

        return is_object($updatedAt) && method_exists($updatedAt, 'toISOString')
            ? $updatedAt->toISOString()
            : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function hasMeaningfulCreateAttributes(array $attributes): bool
    {
        return collect($attributes)
            ->contains(fn (mixed $value): bool => filled($value) && ! is_numeric($value));
    }

    private function isDeniedField(Model $record, string $field): bool
    {
        $baseField = Str::before($field, '.');
        if (in_array($baseField, $record->getHidden(), true)) {
            return true;
        }

        $guarded = array_filter($record->getGuarded(), static fn (string $guardedField): bool => $guardedField !== '*');

        if (in_array($baseField, $guarded, true)) {
            return true;
        }

        return SensitiveDataRedactor::isSensitiveField($baseField);
    }

    private function isClientControlledForeignKey(string $field): bool
    {
        return Str::endsWith($field, ['_id', '_type']) && ! in_array($field, ['id', 'record_key'], true);
    }

    private function isDeniedDirectField(string $field, mixed $value): bool
    {
        return SensitiveDataRedactor::isSensitiveField($field) || $this->isDeniedValue($value);
    }

    private function isDeniedValue(mixed $value): bool
    {
        if (is_array($value)) {
            return collect($value)->contains(fn (mixed $item, int|string $key): bool => (is_string($key) && SensitiveDataRedactor::isSensitiveField($key)) || $this->isDeniedValue($item));
        }

        if (! is_string($value)) {
            return false;
        }

        return SensitiveDataRedactor::text($value) !== $value;
    }

    private function valuesMatch(mixed $current, mixed $requested): bool
    {
        if (is_numeric($current) && is_numeric($requested)) {
            return (float) $current === (float) $requested;
        }

        return $current === $requested;
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if ($this->isDeniedValue($value)) {
            return null;
        }

        if (is_array($value)) {
            return collect($value)
                ->reject(fn (mixed $item, int|string $key): bool => is_string($key) && SensitiveDataRedactor::isSensitiveField($key))
                ->map(fn (mixed $item): mixed => $this->sanitizeValue($item))
                ->all();
        }

        return $value;
    }
}
