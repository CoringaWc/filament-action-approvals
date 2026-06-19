<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use Illuminate\Support\Str;

/**
 * Redige dados sensiveis antes de persistir ou exibir evidencias de aprovacao.
 */
final class SensitiveDataRedactor
{
    /**
     * @return list<string>
     */
    public static function sensitiveFieldNames(): array
    {
        return [
            'access_key',
            'api_key',
            'app_key',
            'authorization',
            'bearer',
            'cookie',
            'cpf',
            'cpf_hash',
            'credential',
            'credentials',
            'environment',
            'password',
            'private_key',
            'raw_token',
            'remember_token',
            'reset_token',
            'secret',
            'senha',
            'session',
            'token',
        ];
    }

    public static function text(string $text): string
    {
        $redacted = self::placeholder();

        return Str::of($text)
            ->replaceMatches('/(?<!\d)\d{3}\.?\d{3}\.?\d{3}-?\d{2}(?!\d)/', $redacted)
            ->replaceMatches('/\b(?:[\w.-]*(?:token|password|senha|secret|credential)|api[_-]?key|access[_-]?key|private[_-]?key|app[_-]?key|authorization|bearer)\b\s*(?:[:=]|\s+)\s*[^\s,;]+/iu', $redacted)
            ->replaceMatches('/\b(?:gh[pousr]_[A-Za-z0-9_]{20,}|github_pat_[A-Za-z0-9_]+|AKIA[0-9A-Z]{16}|eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+)\b/u', $redacted)
            ->toString();
    }

    public static function nullableText(?string $text): ?string
    {
        return $text === null ? null : self::text($text);
    }

    public static function isSensitiveField(string $field): bool
    {
        $normalized = Str::of(Str::snake(str_replace(['-', '.', ' '], '_', $field)))
            ->lower()
            ->toString();
        $compact = str_replace('_', '', $normalized);

        if (in_array($normalized, self::sensitiveFieldNames(), true)) {
            return true;
        }

        if (in_array($compact, [
            'accesskey',
            'apikey',
            'appkey',
            'cpf',
            'cpfhash',
            'privatekey',
            'rawtoken',
            'remembertoken',
            'resettoken',
        ], true)) {
            return true;
        }

        return Str::of($normalized)->contains([
            '_access_key',
            'access_key_',
            '_api_key',
            'api_key_',
            '_app_key',
            'app_key_',
            '_credential',
            'credential_',
            '_cpf',
            'cpf_',
            '_password',
            'password_',
            '_private_key',
            'private_key_',
            '_secret',
            'secret_',
            '_senha',
            'senha_',
            '_token',
            'token_',
        ]);
    }

    private static function placeholder(): string
    {
        return __('filament-action-approvals::approval.infolist.redacted');
    }
}
