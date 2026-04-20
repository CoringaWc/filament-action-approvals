<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\ApproverResolvers;

use CoringaWc\FilamentActionApprovals\Contracts\ApproverResolver;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Support\FormFieldHint;
use CoringaWc\FilamentActionApprovals\Support\TranslatableSelect;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class RoleResolver implements ApproverResolver
{
    /**
     * @param  array{role?: string|list<string>}  $config
     * @return list<int|string>
     */
    public function resolve(array $config, Model $approvable): array
    {
        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();
        $roles = $config['role'] ?? null;

        if (! $roles) {
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
            ->map(function (mixed $userId): int|string {
                return is_string($userId) && ! ctype_digit($userId) ? $userId : (int) $userId;
            })
            ->all();

        return $userIds;
    }

    public static function label(): string
    {
        return __('filament-action-approvals::approval.resolvers.role');
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
        return [
            FormFieldHint::apply(
                TranslatableSelect::apply(
                    Select::make('approver_config.role')
                        ->label(__('filament-action-approvals::approval.resolver_config.role'))
                        ->multiple()
                        ->searchable()
                        ->options(function (): array {
                            $query = Role::query();

                            $excludedRoles = FilamentActionApprovalsPlugin::superAdminRoles();

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
