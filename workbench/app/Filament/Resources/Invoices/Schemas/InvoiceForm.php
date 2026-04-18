<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Invoices\Schemas;

use CoringaWc\FilamentActionApprovals\Support\TranslatableSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Workbench\App\Models\User;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TranslatableSelect::apply(
                Select::make('user_id')
                    ->label(__('workbench::workbench.resources.invoices.fields.requester'))
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->required(),
            ),
            TextInput::make('number')
                ->label(__('workbench::workbench.resources.invoices.fields.number'))
                ->required()
                ->maxLength(255),
            TextInput::make('title')
                ->label(__('workbench::workbench.resources.invoices.fields.title'))
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->label(__('workbench::workbench.resources.invoices.fields.description'))
                ->rows(3),
            TextInput::make('amount')
                ->label(__('workbench::workbench.resources.invoices.fields.amount'))
                ->numeric()
                ->prefix('R$')
                ->required(),
        ]);
    }
}
