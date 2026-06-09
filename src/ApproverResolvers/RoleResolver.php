<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\ApproverResolvers;

use CoringaWc\FilamentActionApprovals\Contracts\ApproverResolver;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Support\FormFieldHint;
use CoringaWc\FilamentActionApprovals\Support\TranslatableSelect;
use CoringaWc\FilamentActionApprovals\Support\UserModelKey;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class RoleResolver implements ApproverResolver
{
    /**
     * @param  array{role?: string|list<string>|null}  $config
     * @return list<int|string>
     */
    public function resolve(array $config, Model $approvable): array
    {
        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();
        $roles = $config['role'] ?? null;

        if (! $roles) {
            return [];
        }

        if (! static::isAvailable($userModel)) {
            return [];
        }

        // Normalize to array (supports legacy single string or new multi-select array)
        $roleNames = is_array($roles) ? $roles : [$roles];

        $query = $userModel::role($roleNames);

        if (config('filament-action-approvals.multi_tenancy.enabled', false) && config('filament-action-approvals.multi_tenancy.scope_approvers', true)) {
            $column = config('filament-action-approvals.multi_tenancy.column', 'company_id');

            if (isset($approvable->{$column})) {
                $query->where($column, $approvable->{$column});
            }
        }

        /** @var list<int|string> $userIds */
        $userIds = $query
            ->pluck($query->getModel()->getKeyName())
            ->map(fn (mixed $userId): int|string|null => UserModelKey::normalize($userId))
            ->filter(fn (mixed $userId): bool => is_int($userId) || is_string($userId))
            ->all();

        return $userIds;
    }

    public static function label(): string
    {
        return __('filament-action-approvals::approval.resolvers.role');
    }

    public static function isAvailable(?string $modelClass = null): bool
    {
        if (! class_exists('Spatie\\Permission\\Models\\Role')) {
            return false;
        }

        $modelClass ??= FilamentActionApprovalsPlugin::resolveUserModel();

        return method_exists($modelClass, 'scopeRole')
            || method_exists($modelClass, 'hasAnyRole')
            || in_array('Spatie\\Permission\\Traits\\HasRoles', class_uses_recursive($modelClass), true);
    }

    /**
     * @return array<int, Component>
     */
    public static function configSchema(): array
    {
        return [
            FormFieldHint::apply(
                TranslatableSelect::apply(
                    Select::make('approver_config.role')
                        ->label(__('filament-action-approvals::approval.resolver_config.role'))
                        ->multiple()
                        ->searchable()
                        ->options(function (): array {
                            $roleModel = config('permission.models.role', 'Spatie\\Permission\\Models\\Role');

                            if (! is_string($roleModel) || ! class_exists($roleModel)) {
                                return [];
                            }

                            $query = $roleModel::query();

                            $excludedRoles = FilamentActionApprovalsPlugin::superAdminRoles();

                            $currentPanel = Filament::getCurrentPanel()?->getId();

                            if (
                                config('filament-action-approvals.roles.limit_to_current_panel', true)
                                && filled($currentPanel)
                                && Schema::hasColumn($query->getModel()->getTable(), 'panel')
                            ) {
                                $query->where('panel', $currentPanel);
                            }

                            if ($excludedRoles !== []) {
                                $query->whereNotIn('name', $excludedRoles);
                            }

                            return $query->pluck('name', 'name')->all();
                        })
                        ->required(),
                ),
                __('filament-action-approvals::approval.flow_hints.resolver_role'),
            ),
        ];
    }
}
