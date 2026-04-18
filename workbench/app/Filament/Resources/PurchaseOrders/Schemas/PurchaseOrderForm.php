<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\PurchaseOrders\Schemas;

use CoringaWc\FilamentActionApprovals\Support\TranslatableSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Workbench\App\Models\User;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TranslatableSelect::apply(
                Select::make('user_id')
                    ->label(__('workbench::workbench.resources.purchase_orders.fields.requester'))
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->required(),
            ),
            TextInput::make('title')
                ->label(__('workbench::workbench.resources.purchase_orders.fields.title'))
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->label(__('workbench::workbench.resources.purchase_orders.fields.description'))
                ->rows(3),
            TextInput::make('amount')
                ->label(__('workbench::workbench.resources.purchase_orders.fields.amount'))
                ->numeric()
                ->prefix('R$')
                ->required(),
        ]);
    }
}
