<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Expenses\Schemas;

use CoringaWc\FilamentActionApprovals\Support\TranslatableSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Workbench\App\Models\User;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TranslatableSelect::apply(
                Select::make('user_id')
                    ->label(__('workbench::workbench.resources.expenses.fields.requester'))
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->required(),
            ),
            TextInput::make('title')
                ->label(__('workbench::workbench.resources.expenses.fields.title'))
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->label(__('workbench::workbench.resources.expenses.fields.description'))
                ->rows(3),
            TranslatableSelect::apply(
                Select::make('category')
                    ->label(__('workbench::workbench.resources.expenses.fields.category'))
                    ->options([
                        'travel' => __('workbench::workbench.resources.expenses.categories.travel'),
                        'supplies' => __('workbench::workbench.resources.expenses.categories.supplies'),
                        'equipment' => __('workbench::workbench.resources.expenses.categories.equipment'),
                        'training' => __('workbench::workbench.resources.expenses.categories.training'),
                    ])
                    ->searchable()
                    ->preload(),
            ),
            TextInput::make('amount')
                ->label(__('workbench::workbench.resources.expenses.fields.amount'))
                ->numeric()
                ->prefix('R$')
                ->required(),
        ]);
    }
}
