<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources;

use CoringaWc\FilamentAcl\Attributes\PermissionSubject;
use CoringaWc\FilamentAcl\Resources\Concerns\HasResourcePermissions;
use CoringaWc\FilamentAcl\Support\Utils;
use CoringaWc\FilamentActionApprovals\Support\TranslatableSelect;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Workbench\App\Filament\Resources\UserResource\Pages\CreateUser;
use Workbench\App\Filament\Resources\UserResource\Pages\EditUser;
use Workbench\App\Filament\Resources\UserResource\Pages\ListUsers;
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
        return $schema->components([
            TextInput::make('name')
                ->label(__('workbench::workbench.resources.users.fields.name'))
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label(__('workbench::workbench.resources.users.fields.email'))
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            TextInput::make('password')
                ->label(__('workbench::workbench.resources.users.fields.password'))
                ->password()
                ->revealable()
                ->required()
                ->visibleOn(Operation::Create)
                ->maxLength(255),
            TranslatableSelect::apply(
                Select::make('roles')
                    ->label(__('workbench::workbench.resources.users.fields.roles'))
                    ->disabled(static fn (?User $record): bool => $record?->hasRole(Utils::getProtectedRoleName()) ?? false)
                    ->relationship(
                        name: 'roles',
                        titleAttribute: 'name',
                        modifyQueryUsing: static fn (Builder $query): Builder => Utils::scopeVisibleRoles($query->orderBy('name')),
                    )
                    ->getOptionLabelFromRecordUsing(static fn (Role $record): string => static::translateRoleName($record->name))
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->loadStateFromRelationshipsUsing(static function (Select $component, User $record): void {
                        $component->state(
                            $record->roles()
                                ->whereNotIn('id', Utils::getHiddenRoleIds(Filament::getCurrentPanel()?->getId()))
                                ->pluck('id')
                                ->all(),
                        );
                    })
                    ->saveRelationshipsUsing(static function (Select $component, User $record, mixed $state): void {
                        /** @var array<int, int|string> $roleIds */
                        $roleIds = array_values(array_filter(is_array($state) ? $state : []));
                        $mergedRoleIds = Utils::mergeHiddenRoleIds($record, $roleIds, Filament::getCurrentPanel()?->getId());

                        $record->syncRoles(
                            Role::query()
                                ->whereKey($mergedRoleIds)
                                ->get(),
                        );
                    }),
            ),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('workbench::workbench.resources.users.columns.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('workbench::workbench.resources.users.columns.email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('visible_roles')
                    ->label(__('workbench::workbench.resources.users.columns.roles'))
                    ->badge()
                    ->state(static fn (User $record): string => $record->roles
                        ->pluck('name')
                        ->map(static fn (string $name): string => static::translateRoleName($name))
                        ->join(', ')),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(static fn (User $record): bool => ! $record->hasRole(Utils::getProtectedRoleName())),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    protected static function translateRoleName(string $name): string
    {
        $key = 'workbench::workbench.roles.'.$name;
        $translated = __($key);

        return $translated === $key ? Str::headline($name) : $translated;
    }
}
