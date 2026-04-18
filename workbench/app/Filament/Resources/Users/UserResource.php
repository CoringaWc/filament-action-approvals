<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Users;

use CoringaWc\FilamentAcl\Attributes\PermissionSubject;
use CoringaWc\FilamentAcl\Resources\Concerns\HasResourcePermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Workbench\App\Filament\Resources\Users\Pages\CreateUser;
use Workbench\App\Filament\Resources\Users\Pages\EditUser;
use Workbench\App\Filament\Resources\Users\Pages\ListUsers;
use Workbench\App\Filament\Resources\Users\Schemas\UserForm;
use Workbench\App\Filament\Resources\Users\Tables\UsersTable;
use Workbench\App\Models\User;

#[PermissionSubject('user')]
class UserResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = User::class;

    /**
     * @return array<int, string>
     */
    public static function getPermissionActions(): array
    {
        return [
            'viewAny',
            'view',
            'create',
            'update',
            'delete',
        ];
    }

    public static function getModelLabel(): string
    {
        return __('workbench::workbench.resources.users.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('workbench::workbench.resources.users.plural_model_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('workbench::workbench.resources.users.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('workbench::workbench.resources.users.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
