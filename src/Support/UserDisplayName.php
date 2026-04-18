<?php

namespace CoringaWc\FilamentActionApprovals\Support;

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Model;

class UserDisplayName
{
    /**
     * Resolve the display name for a user model.
     *
     * Uses getFilamentName() if the user implements HasName,
     * otherwise falls back to the 'name' attribute.
     */
    public static function resolve(?Model $user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($user instanceof HasName) {
            return $user->getFilamentName();
        }

        return $user->getAttribute('name');
    }

    /**
     * Resolve display names for a collection of user IDs.
     *
     * Returns a comma-separated string of display names.
     */
    public static function resolveMany(array $userIds, ?string $fallback = null): string
    {
        if (empty($userIds)) {
            return $fallback ?? '';
        }

        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();

        $users = $userModel::whereIn('id', $userIds)->get();

        $names = $users->map(fn (Model $user): string => static::resolve($user) ?? '')->filter()->join(', ');

        return $names ?: ($fallback ?? '');
    }
}
