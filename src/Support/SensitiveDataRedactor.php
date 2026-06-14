<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use Illuminate\Support\Str;

/**
 * Redige dados sensiveis antes de persistir ou exibir evidencias de aprovacao.
 */
final class SensitiveDataRedactor
{
    public static function text(string $text): string
    {
        $redacted = self::placeholder();

        return Str::of($text)
            ->replaceMatches('/(?<!\d)\d{3}\.?\d{3}\.?\d{3}-?\d{2}(?!\d)/', $redacted)
            ->replaceMatches('/\b(?:[\w.-]*token|password|senha|secret|credential)\b\s*(?:[:=]|\s+)\s*[^\s,;]+/iu', $redacted)
            ->toString();
    }

    public static function nullableText(?string $text): ?string
    {
        return $text === null ? null : self::text($text);
    }

    public static function isSensitiveField(string $field): bool
    {
        return Str::of($field)
            ->lower()
            ->contains(['cpf', 'password', 'senha', 'token', 'secret', 'credential']);
    }

    private static function placeholder(): string
    {
        return __('filament-action-approvals::approval.infolist.redacted');
    }
}
