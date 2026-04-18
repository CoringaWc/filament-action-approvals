<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Users\Tables;

use CoringaWc\FilamentAcl\Support\Utils;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Workbench\App\Filament\Resources\Users\Schemas\UserForm;
use Workbench\App\Models\User;

class UsersTable
{
    public static function configure(Table $table): Table
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
                        ->map(static fn (string $name): string => UserForm::translateRoleName($name))
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
}
