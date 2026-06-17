<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class CurrentPanelUser
{
    public static function model(): ?Model
    {
        try {
            $user = Filament::auth()->user();
        } catch (Throwable) {
            return null;
        }

        return $user instanceof Model ? $user : null;
    }

    public static function id(): int|string|null
    {
        $user = self::model();

        if (! $user instanceof Model) {
            return null;
        }

        return UserModelKey::normalize($user->getKey());
    }
}
