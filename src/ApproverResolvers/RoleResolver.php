<?php

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
     * @param  array{role?: string}  $config
     * @return list<int>
     */
    public function resolve(array $config, Model $approvable): array
    {
        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();
        $roleName = $config['role'] ?? null;

        if (! $roleName) {
            return [];
        }

        $query = $userModel::role($roleName);

        if (config('filament-action-approvals.multi_tenancy.enabled', false) && config('filament-action-approvals.multi_tenancy.scope_approvers', true)) {
            $column = config('filament-action-approvals.multi_tenancy.column', 'company_id');

            if (isset($approvable->{$column})) {
                $query->where($column, $approvable->{$column});
            }
        }

        /** @var list<int> $userIds */
        $userIds = $query
            ->pluck($query->getModel()->getKeyName())
            ->map(fn (mixed $userId): int => (int) $userId)
            ->all();

        return $userIds;
    }

    public static function label(): string
    {
        return __('filament-action-approvals::approval.resolvers.role');
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
                        ->searchable()
                        ->options(fn (): array => Role::query()->pluck('name', 'name')->all())
                        ->required(),
                ),
                __('filament-action-approvals::approval.flow_hints.resolver_role'),
            ),
        ];
    }
}
