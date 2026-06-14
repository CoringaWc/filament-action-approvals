<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class ApprovalCrudPayload
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $allowedFields
     * @return array<string, mixed>
     */
    public function editPayload(Model $record, array $data, array $allowedFields): array
    {
        if ($allowedFields === []) {
            return [];
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

        if ($payload === []) {
            return [];
        }

        return [
            ...$payload,
            'changed_fields' => $changedFields,
            'approval_payload_diff' => $diff,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deletePayload(): array
    {
        return [
            'changed_fields' => [],
        ];
    }

    private function isDeniedField(Model $record, string $field): bool
    {
        $baseField = Str::before($field, '.');
        $normalized = Str::of($baseField)->lower()->toString();

        if (in_array($baseField, $record->getHidden(), true)) {
            return true;
        }

        $guarded = array_filter($record->getGuarded(), static fn (string $guardedField): bool => $guardedField !== '*');

        if (in_array($baseField, $guarded, true)) {
            return true;
        }

        return Str::of($normalized)->contains([
            'cpf',
            'password',
            'senha',
            'remember_token',
            'reset_token',
            'token',
            'raw_token',
            'secret',
            'credential',
        ]);
    }

    private function isDeniedValue(mixed $value): bool
    {
        if (is_array($value)) {
            return collect($value)->contains(fn (mixed $item): bool => $this->isDeniedValue($item));
        }

        if (! is_string($value)) {
            return false;
        }

        $normalized = Str::of($value)->lower()->squish()->toString();

        if (Str::of($normalized)->contains(['cpf', 'password', 'senha', 'token', 'secret', 'credential'])) {
            return true;
        }

        return Str::isMatch('/(?:^|\D)\d{3}\.?\d{3}\.?\d{3}-?\d{2}(?:\D|$)/', $value);
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
                ->reject(fn (mixed $item, int|string $key): bool => is_string($key) && Str::of($key)->lower()->contains(['cpf', 'password', 'senha', 'token', 'secret', 'credential']))
                ->map(fn (mixed $item): mixed => $this->sanitizeValue($item))
                ->all();
        }

        return $value;
    }
}
