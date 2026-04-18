<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Invoices\Tables;

use CoringaWc\FilamentActionApprovals\Columns\ApprovalStatusColumn;
use CoringaWc\FilamentActionApprovals\Support\DateDisplay;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Workbench\App\States\Invoice\InvoiceState;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label(__('workbench::workbench.resources.invoices.columns.number'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('workbench::workbench.resources.invoices.columns.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('workbench::workbench.resources.invoices.columns.requester'))
                    ->sortable(),
                TextColumn::make('amount')
                    ->label(__('workbench::workbench.resources.invoices.columns.amount'))
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('workbench::workbench.resources.invoices.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (InvoiceState $state): string => $state->getLabel())
                    ->color(fn (InvoiceState $state): string => $state->getColor()),
                ApprovalStatusColumn::make(),
                DateDisplay::column(
                    TextColumn::make('created_at')
                        ->label(__('workbench::workbench.resources.invoices.columns.created_at')),
                )->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
