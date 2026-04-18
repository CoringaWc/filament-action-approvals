<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Users\Schemas;

use CoringaWc\FilamentAcl\Support\Utils;
use CoringaWc\FilamentActionApprovals\Support\TranslatableSelect;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Workbench\App\Models\User;

class UserForm
{
    public static function configure(Schema $schema): Schema
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
                    ->getOptionLabelFromRecordUsing(static fn (Role $record): string => self::translateRoleName($record->name))
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

    public static function translateRoleName(string $name): string
    {
        $key = 'workbench::workbench.roles.'.$name;
        $translated = __($key);

        return $translated === $key ? Str::headline($name) : $translated;
    }
}
