<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Contracts;

use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;

interface ApproverResolver
{
    /**
     * Resolve the user IDs who should approve this step.
     *
     * @param  array<string, mixed>  $config
     * @return list<int|string>
     */
    public function resolve(array $config, Model $approvable): array;

    /**
     * Human-readable label for the admin UI.
     */
    public static function label(): string;

    /**
     * Filament form schema for configuring this resolver in the flow builder.
     *
     * @return array<int, Component>
     */
    public static function configSchema(): array;

    /**
     * Whether this resolver is available for the given model.
     *
     * Used by the flow builder to filter out resolver types that are
     * not applicable for the selected approvable model. Return true
     * when no model is selected (show all resolver options).
     *
     * @param  class-string|null  $modelClass  The selected approvable model FQCN
     */
    public static function isAvailable(?string $modelClass = null): bool;
}
