<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use Illuminate\Database\Eloquent\Model;

final class PrivilegedUserAccess
{
    /**
     * @var array<string, bool>
     */
    private array $superAdminChecks = [];

    /**
     * @param  class-string<Model>  $userModel
     * @param  array{enabled: bool, roles: list<string>, user_ids: list<int|string>, hide_from_selects: bool, apply_directly: bool}  $config
     */
    public function isSuperAdmin(int|string|null $userId, string $userModel, array $config): bool
    {
        if (! $config['enabled']) {
            return false;
        }

        $normalizedUserId = UserModelKey::normalize($userId);

        if ($normalizedUserId === null) {
            return false;
        }

        if (in_array($normalizedUserId, $config['user_ids'], true)) {
            return true;
        }

        if ($config['roles'] === []) {
            return false;
        }

        $cacheKey = $this->cacheKey($normalizedUserId, $userModel, $config);

        if (array_key_exists($cacheKey, $this->superAdminChecks)) {
            return $this->superAdminChecks[$cacheKey];
        }

        $user = $this->userForRoleCheck($normalizedUserId, $userModel);

        if (! $user || ! method_exists($user, 'hasAnyRole')) {
            return $this->superAdminChecks[$cacheKey] = false;
        }

        /** @var bool $hasRole */
        $hasRole = $user->hasAnyRole($config['roles']);

        return $this->superAdminChecks[$cacheKey] = $hasRole;
    }

    public function flush(): void
    {
        $this->superAdminChecks = [];
    }

    /**
     * @param  class-string<Model>  $userModel
     * @param  array{enabled: bool, roles: list<string>, user_ids: list<int|string>, hide_from_selects: bool, apply_directly: bool}  $config
     */
    private function cacheKey(int|string $userId, string $userModel, array $config): string
    {
        return implode('|', [
            $userModel,
            (string) $userId,
            hash('xxh128', serialize([$config['roles'], $config['user_ids']])),
        ]);
    }

    /**
     * @param  class-string<Model>  $userModel
     */
    private function userForRoleCheck(int|string $userId, string $userModel): ?Model
    {
        $currentPanelUser = CurrentPanelUser::model();

        if ($currentPanelUser instanceof $userModel && UserModelKey::normalize($currentPanelUser->getKey()) === $userId) {
            return $currentPanelUser;
        }

        /** @var Model|null $user */
        $user = $userModel::query()->find($userId);

        return $user;
    }
}
