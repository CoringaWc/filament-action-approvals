<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\ApproverResolvers;

use CoringaWc\FilamentActionApprovals\Contracts\ApproverResolver;
use CoringaWc\FilamentActionApprovals\Support\FormFieldHint;
use CoringaWc\FilamentActionApprovals\Support\TranslatableSelect;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;

class CustomRuleResolver implements ApproverResolver
{
    /**
     * @param  array{custom_rule?: string}  $config
     * @return list<int>
     */
    public function resolve(array $config, Model $approvable): array
    {
        $ruleKey = $config['custom_rule'] ?? null;

        if (! $ruleKey || ! method_exists($approvable, 'approvalCustomRules')) {
            return [];
        }

        /** @var array<string, \Closure> $rules */
        $rules = $approvable::approvalCustomRules(); // @phpstan-ignore method.staticCall

        if (! isset($rules[$ruleKey])) {
            return [];
        }

        $userIds = [];

        foreach ($rules[$ruleKey]($approvable) as $userId) {
            if (is_int($userId)) {
                $userIds[] = $userId;

                continue;
            }

            if (is_string($userId) && ctype_digit($userId)) {
                $userIds[] = (int) $userId;
            }
        }

        return $userIds;
    }

    public static function label(): string
    {
        return __('filament-action-approvals::approval.resolvers.custom_rule');
    }

    /**
     * Whether the custom rule resolver is available for the given model.
     *
     * Returns false when no model is selected — custom rules are model-specific,
     * so they should only appear when a concrete model is chosen.
     * Returns false when the model does not define approvalCustomRules()
     * or when the method returns an empty array.
     */
    public static function isAvailable(?string $modelClass = null): bool
    {
        if ($modelClass === null) {
            return false;
        }

        if (! method_exists($modelClass, 'approvalCustomRules')) {
            return false;
        }

        /** @var array<string, \Closure> $rules */
        $rules = $modelClass::approvalCustomRules();

        return $rules !== [];
    }

    /**
     * @return array<int, Component>
     */
    public static function configSchema(): array
    {
        return [
            FormFieldHint::apply(
                TranslatableSelect::apply(
                    Select::make('approver_config.custom_rule')
                        ->label(__('filament-action-approvals::approval.resolver_config.custom_rule'))
                        ->searchable()
                        ->options(function (Get $get): array {
                            $modelClass = $get('../../approvable_type');

                            if (! $modelClass || ! is_string($modelClass) || ! method_exists($modelClass, 'approvalCustomRules')) {
                                return [];
                            }

                            /** @var array<string, \Closure> $rules */
                            $rules = $modelClass::approvalCustomRules();

                            return collect(array_keys($rules))
                                ->mapWithKeys(fn (string $key): array => [$key => str($key)->headline()->toString()])
                                ->all();
                        })
                        ->required(),
                ),
                __('filament-action-approvals::approval.flow_hints.resolver_custom_rule'),
            ),
        ];
    }
}
