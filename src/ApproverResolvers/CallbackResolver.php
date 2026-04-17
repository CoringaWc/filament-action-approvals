<?php

namespace CoringaWc\FilamentActionApprovals\ApproverResolvers;

use Closure;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;
use CoringaWc\FilamentActionApprovals\Contracts\ApproverResolver;

class CallbackResolver implements ApproverResolver
{
    /** @var array<string, Closure> */
    protected static array $callbacks = [];

    public static function register(string $name, Closure $callback): void
    {
        static::$callbacks[$name] = $callback;
    }

    /**
     * @return array<string, Closure>
     */
    public static function getRegisteredCallbacks(): array
    {
        return static::$callbacks;
    }

    public function resolve(array $config, Model $approvable): array
    {
        $callbackName = $config['callback'] ?? null;
        $callback = static::$callbacks[$callbackName] ?? null;

        if (! $callback) {
            return [];
        }

        return (array) $callback($approvable, $config);
    }

    public static function label(): string
    {
        return __('filament-action-approvals::approval.resolvers.callback');
    }

    public static function configSchema(): array
    {
        return [
            Select::make('approver_config.callback')
                ->label(__('filament-action-approvals::approval.resolver_config.resolver'))
                ->options(fn () => collect(array_keys(static::$callbacks))
                    ->mapWithKeys(fn ($k) => [$k => str($k)->headline()->toString()])
                    ->all()
                )
                ->required(),
        ];
    }
}
