<?php

namespace CoringaWc\FilamentActionApprovals\Contracts;

use Filament\Forms\Components\Component;
use Illuminate\Database\Eloquent\Model;

interface ApproverResolver
{
    /**
     * Resolve the user IDs who should approve this step.
     *
     * @param  array<string, mixed>  $config
     * @return array<int>
     */
    public function resolve(array $config, Model $approvable): array;

    /**
     * Human-readable label for the admin UI.
     */
    public static function label(): string;

    /**
     * Filament form schema for configuring this resolver in the flow builder.
     *
     * @return array<Component>
     */
    public static function configSchema(): array;
}
