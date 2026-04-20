<?php

declare(strict_types=1);

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
     * @return list<int|string>
     */
    public function resolve(array $config, Model $approvable): array
    {
        $userIds = [];

        foreach ($config['user_ids'] ?? [] as $userId) {
            if (is_int($userId)) {
                $userIds[] = $userId;

                continue;
            }

            // String userId: normalize numeric strings to int, keep UUID strings as-is
            $userIds[] = ctype_digit($userId) ? (int) $userId : $userId;
        }

        return $userIds;
    }

    public static function label(): string
    {
        return __('filament-action-approvals::approval.resolvers.user');
    }

    public static function isAvailable(?string $modelClass = null): bool
    {
        return true;
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
                            $query = $userModel::query();

                            // Exclude super admin users from options
                            $excludedIds = FilamentActionApprovalsPlugin::superAdminUserIds();

                            if ($excludedIds !== []) {
                                $query->whereNotIn($query->getModel()->getKeyName(), $excludedIds);
                            }

                            // Exclude users with super admin roles
                            $excludedRoles = FilamentActionApprovalsPlugin::superAdminRoles();

                            if ($excludedRoles !== [] && method_exists($userModel, 'role')) {
                                $query->whereDoesntHave('roles', function ($q) use ($excludedRoles): void {
                                    $q->whereIn('name', $excludedRoles);
                                });
                            }

                            return $query->get()
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
