<?php

namespace CoringaWc\FilamentActionApprovals\ApproverResolvers;

use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;
use CoringaWc\FilamentActionApprovals\Contracts\ApproverResolver;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;

class UserResolver implements ApproverResolver
{
    public function resolve(array $config, Model $approvable): array
    {
        return $config['user_ids'] ?? [];
    }

    public static function label(): string
    {
        return __('filament-action-approvals::approval.resolvers.user');
    }

    public static function configSchema(): array
    {
        $userModel = FilamentActionApprovalsPlugin::resolveUserModel();

        return [
            Select::make('approver_config.user_ids')
                ->label(__('filament-action-approvals::approval.resolver_config.users'))
                ->multiple()
                ->searchable()
                ->options(fn () => $userModel::pluck('name', 'id'))
                ->required(),
        ];
    }
}
