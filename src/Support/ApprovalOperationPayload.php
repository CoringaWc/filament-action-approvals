<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ApprovalOperationPayload
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $allowedFields
     * @return array<string, mixed>
     */
    public function editPayload(Model $record, array $data, array $allowedFields): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->editPayloadData($record, $data, $allowedFields)['payload'];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $allowedFields
     * @return list<string>
     */
    public function editFields(Model $record, array $data, array $allowedFields): array
    {
        /** @var list<string> $fields */
        $fields = $this->editPayloadData($record, $data, $allowedFields)['fields'];

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $allowedFields
     * @return list<array{field: string, current: mixed, requested: mixed}>
     */
    public function editDiff(Model $record, array $data, array $allowedFields): array
    {
        /** @var list<array{field: string, current: mixed, requested: mixed}> $diff */
        $diff = $this->editPayloadData($record, $data, $allowedFields)['diff'];

        return $diff;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $allowedFields
     * @return array{payload: array<string, mixed>, fields: list<string>, diff: list<array{field: string, current: mixed, requested: mixed}>}
     */
    private function editPayloadData(Model $record, array $data, array $allowedFields): array
    {
        if ($allowedFields === []) {
            return [
                'payload' => [],
                'fields' => [],
                'diff' => [],
            ];
        }

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

        return [
            'payload' => $payload,
            'fields' => $changedFields,
            'diff' => $diff,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deletePayload(): array
    {
        return [];
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
            return (string) $current === (string) $requested;
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
