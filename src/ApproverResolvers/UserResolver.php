<?php

namespace CoringaWc\FilamentActionApprovals\ApproverResolvers;

use CoringaWc\FilamentActionApprovals\Contracts\ApproverResolver;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Support\FormFieldHint;
use CoringaWc\FilamentActionApprovals\Support\TranslatableSelect;
use CoringaWc\FilamentActionApprovals\Support\UserDisplayName;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;

class UserResolver implements ApproverResolver
{
    /**
     * @param  array{user_ids?: list<int|string>}  $config
     * @return list<int>
     */
    public function resolve(array $config, Model $approvable): array
    {
        $userIds = [];

        foreach ($config['user_ids'] ?? [] as $userId) {
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
        return __('filament-action-approvals::approval.resolvers.user');
    }

    /**
     * @return array<int, Component>
     */
    public static function configSchema(): array
    {
        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();

        return [
            FormFieldHint::apply(
                TranslatableSelect::apply(
                    Select::make('approver_config.user_ids')
                        ->label(__('filament-action-approvals::approval.resolver_config.users'))
                        ->multiple()
                        ->searchable()
                        ->options(function () use ($userModel): array {
                            return $userModel::all()
                                ->mapWithKeys(fn (Model $user): array => [$user->getKey() => UserDisplayName::resolve($user)])
                                ->all();
                        })
                        ->required(),
                ),
                __('filament-action-approvals::approval.flow_hints.resolver_users'),
            ),
        ];
    }
}
