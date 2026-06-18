<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;

final class ApproverUserOptions
{
    /**
     * @var array<string, array<int|string, string>>
     */
    private array $options = [];

    /**
     * @param  class-string<Model>  $userModel
     * @param  list<int|string>  $excludedIds
     * @param  list<string>  $excludedRoles
     * @param  Closure(): array<int|string, string>  $callback
     * @return array<int|string, string>
     */
    public function remember(
        string $userModel,
        array $excludedIds,
        array $excludedRoles,
        Closure $callback,
    ): array {
        $cacheKey = implode('|', [
            $userModel,
            hash('xxh128', serialize([$excludedIds, $excludedRoles])),
        ]);

        if (array_key_exists($cacheKey, $this->options)) {
            return $this->options[$cacheKey];
        }

        return $this->options[$cacheKey] = $callback();
    }

    public function flush(): void
    {
        $this->options = [];
    }
}
