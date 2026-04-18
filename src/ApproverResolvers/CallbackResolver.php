<?php

namespace CoringaWc\FilamentActionApprovals\ApproverResolvers;

use Closure;
use CoringaWc\FilamentActionApprovals\Contracts\ApproverResolver;
use CoringaWc\FilamentActionApprovals\Support\FormFieldHint;
use CoringaWc\FilamentActionApprovals\Support\TranslatableSelect;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;

class CallbackResolver implements ApproverResolver
{
    /** @var array<string, Closure(Model, array<string, mixed>): list<int|string>> */
    protected static array $callbacks = [];

    /**
     * @param  Closure(Model, array<string, mixed>): list<int|string>  $callback
     */
    public static function register(string $name, Closure $callback): void
    {
        static::$callbacks[$name] = $callback;
    }

    /**
     * @return array<string, Closure(Model, array<string, mixed>): list<int|string>>
     */
    public static function getRegisteredCallbacks(): array
    {
        return static::$callbacks;
    }

    /**
     * @param  array{callback?: string}  $config
     * @return list<int>
     */
    public function resolve(array $config, Model $approvable): array
    {
        $callbackName = $config['callback'] ?? null;
        $callback = static::$callbacks[$callbackName] ?? null;

        if (! $callback) {
            return [];
        }

        $userIds = [];

        foreach ($callback($approvable, $config) as $userId) {
            if (is_int($userId)) {
                $userIds[] = $userId;

                continue;
            }

            if (ctype_digit($userId)) {
                $userIds[] = (int) $userId;
            }
        }

        return $userIds;
    }

    public static function label(): string
    {
        return __('filament-action-approvals::approval.resolvers.callback');
    }

    /**
     * @return array<int, Component>
     */
    public static function configSchema(): array
    {
        return [
            FormFieldHint::apply(
                TranslatableSelect::apply(
                    Select::make('approver_config.callback')
                        ->label(__('filament-action-approvals::approval.resolver_config.resolver'))
                        ->searchable()
                        ->options(fn (): array => collect(array_keys(static::$callbacks))
                            ->mapWithKeys(fn (string $key): array => [$key => str($key)->headline()->toString()])
                            ->all())
                        ->required(),
                ),
                __('filament-action-approvals::approval.flow_hints.resolver_callback'),
            ),
        ];
    }
}
