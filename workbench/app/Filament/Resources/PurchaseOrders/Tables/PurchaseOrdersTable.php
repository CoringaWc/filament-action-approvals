<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\PurchaseOrders\Tables;

use CoringaWc\FilamentActionApprovals\Columns\ApprovalStatusColumn;
use CoringaWc\FilamentActionApprovals\Support\DateDisplay;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('workbench::workbench.resources.purchase_orders.columns.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('workbench::workbench.resources.purchase_orders.columns.requester'))
                    ->sortable(),
                TextColumn::make('amount')
                    ->label(__('workbench::workbench.resources.purchase_orders.columns.amount'))
                    ->money('BRL')
                    ->sortable(),
                ApprovalStatusColumn::make(),
                DateDisplay::column(
                    TextColumn::make('created_at')
                        ->label(__('workbench::workbench.resources.purchase_orders.columns.created_at')),
                )->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
