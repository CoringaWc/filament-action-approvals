<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\ApproverResolvers;

use CoringaWc\FilamentActionApprovals\Contracts\ApproverResolver;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Support\ApproverUserOptions;
use CoringaWc\FilamentActionApprovals\Support\FormFieldHint;
use CoringaWc\FilamentActionApprovals\Support\TranslatableSelect;
use CoringaWc\FilamentActionApprovals\Support\UserDisplayName;
use CoringaWc\FilamentActionApprovals\Support\UserModelKey;
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
            $normalizedUserId = UserModelKey::normalize($userId);

            if (! is_int($normalizedUserId) && ! is_string($normalizedUserId)) {
                continue;
            }

            $userIds[] = $normalizedUserId;
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
        /** @var class-string<Model> $userModel */
        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();

        return [
            FormFieldHint::apply(
                TranslatableSelect::apply(
                    Select::make('approver_config.user_ids')
                        ->label(__('filament-action-approvals::approval.resolver_config.users'))
                        ->multiple()
                        ->searchable()
                        ->options(function () use ($userModel): array {
                            $excludedIds = FilamentActionApprovalsPlugin::superAdminUserIds();
                            $excludedRoles = FilamentActionApprovalsPlugin::superAdminRoles();

                            return app(ApproverUserOptions::class)->remember(
                                userModel: $userModel,
                                excludedIds: $excludedIds,
                                excludedRoles: $excludedRoles,
                                callback: static function () use ($userModel, $excludedIds, $excludedRoles): array {
                                    $query = $userModel::query();

                                    // Exclude super admin users from options.
                                    if ($excludedIds !== []) {
                                        $query->whereNotIn($query->getModel()->getKeyName(), $excludedIds);
                                    }

                                    // Exclude users with super admin roles.
                                    if ($excludedRoles !== [] && method_exists($userModel, 'role')) {
                                        $query->whereDoesntHave('roles', function ($q) use ($excludedRoles): void {
                                            $q->whereIn('name', $excludedRoles);
                                        });
                                    }

                                    return $query->get()
                                        ->mapWithKeys(fn (Model $user): array => [$user->getKey() => UserDisplayName::resolve($user) ?? (string) $user->getKey()])
                                        ->all();
                                },
                            );
                        })
                        ->required(),
                ),
                __('filament-action-approvals::approval.flow_hints.resolver_users'),
            ),
        ];
    }
}
