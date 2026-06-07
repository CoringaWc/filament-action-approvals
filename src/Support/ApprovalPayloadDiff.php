<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use BackedEnum;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Builds a generic, safe summary of submitted approval payload changes.
 */
final class ApprovalPayloadDiff
{
    /**
     * @return list<array{field: string, current: string|null, requested: string|null}>
     */
    public static function forApproval(Approval $approval): array
    {
        $payload = self::payload($approval);

        if ($payload === []) {
            return [];
        }

        $changedFields = self::changedFields($payload);

        if ($changedFields !== []) {
            return self::changedFieldRows($approval, $payload, $changedFields);
        }

        return self::payloadRows($approval, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(Approval $approval): array
    {
        $payload = Arr::get($approval->metadata ?? [], 'payload', []);

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private static function changedFields(array $payload): array
    {
        $fields = Arr::get($payload, 'changed_fields', []);

        if (! is_array($fields)) {
            return [];
        }

        /** @var list<string> $changedFields */
        $changedFields = collect($fields)
            ->filter(fn (mixed $field): bool => is_string($field) && filled($field) && ! self::isSecretField($field))
            ->values()
            ->all();

        return $changedFields;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $changedFields
     * @return list<array{field: string, current: string|null, requested: string|null}>
     */
    private static function changedFieldRows(Approval $approval, array $payload, array $changedFields): array
    {
        /** @var list<array{field: string, current: string|null, requested: string|null}> $rows */
        $rows = collect($changedFields)
            ->map(fn (string $field): array => self::row(
                self::fieldLabel($field),
                self::currentValue($approval, $field),
                self::requestedValue($payload, $field),
            ))
            ->values()
            ->all();

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{field: string, current: string|null, requested: string|null}>
     */
    private static function payloadRows(Approval $approval, array $payload): array
    {
        /** @var list<array{field: string, current: string|null, requested: string|null}> $rows */
        $rows = collect($payload)
            ->reject(fn (mixed $value, int|string $field): bool => self::isMetadataOnlyField((string) $field) || self::isSecretField((string) $field))
            ->map(fn (mixed $value, int|string $field): array => self::row(
                self::fieldLabel((string) $field),
                self::currentValue($approval, (string) $field),
                self::isRedactedField((string) $field) ? self::redactedPlaceholder() : self::displayValue($value),
            ))
            ->values()
            ->all();

        return $rows;
    }

    private static function currentValue(Approval $approval, string $field): ?string
    {
        if (self::isRedactedField($field)) {
            return self::redactedPlaceholder();
        }

        $approvable = $approval->relationLoaded('approvable')
            ? $approval->approvable
            : $approval->approvable()->first();

        if (! $approvable instanceof Model) {
            return null;
        }

        if (in_array(Str::before($field, '.'), $approvable->getHidden(), true)) {
            return self::redactedPlaceholder();
        }

        if (! Arr::has($approvable->getAttributes(), Str::before($field, '.'))) {
            return null;
        }

        return self::displayValue(data_get($approvable, $field));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function requestedValue(array $payload, string $field): ?string
    {
        if (self::isRedactedField($field)) {
            return self::redactedPlaceholder();
        }

        if (! Arr::exists($payload, $field)) {
            return self::changedPlaceholder();
        }

        return self::displayValue(Arr::get($payload, $field));
    }

    private static function displayValue(mixed $value): ?string
    {
        if ($value instanceof HasLabel) {
            $label = $value->getLabel();

            return $label instanceof Htmlable ? strip_tags($label->toHtml()) : $label;
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if (is_bool($value)) {
            return $value
                ? __('filament-action-approvals::approval.infolist.boolean_true')
                : __('filament-action-approvals::approval.infolist.boolean_false');
        }

        if (is_scalar($value)) {
            $value = Str::of((string) $value)->trim()->toString();

            return $value === '' ? null : $value;
        }

        if (is_array($value)) {
            return trans_choice('filament-action-approvals::approval.infolist.items_count', count($value), [
                'count' => count($value),
            ]);
        }

        return null;
    }

    private static function fieldLabel(string $field): string
    {
        $normalized = Str::of($field)->lower()->toString();

        if ($normalized === 'cpf') {
            return 'CPF';
        }

        if ($normalized === 'cnpj') {
            return 'CNPJ';
        }

        return Str::of($field)
            ->replace('_', ' ')
            ->replace('.', ' ')
            ->title()
            ->toString();
    }

    /**
     * @return array{field: string, current: string|null, requested: string|null}
     */
    private static function row(string $field, ?string $current, ?string $requested): array
    {
        return [
            'field' => $field,
            'current' => $current,
            'requested' => $requested,
        ];
    }

    private static function isMetadataOnlyField(string $field): bool
    {
        return in_array($field, ['changed_fields'], true);
    }

    private static function isSecretField(string $field): bool
    {
        return Str::of($field)
            ->lower()
            ->contains(['password', 'token', 'secret', 'credential']);
    }

    private static function isRedactedField(string $field): bool
    {
        return self::isSecretField($field)
            || Str::of($field)
                ->lower()
                ->contains(['cpf', 'cnpj', 'ssn', 'tax_identifier']);
    }

    private static function changedPlaceholder(): string
    {
        return __('filament-action-approvals::approval.infolist.changed');
    }

    private static function redactedPlaceholder(): string
    {
        return __('filament-action-approvals::approval.infolist.redacted');
    }
}
