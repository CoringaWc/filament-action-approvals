<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Expenses\Tables;

use CoringaWc\FilamentActionApprovals\Columns\ApprovalStatusColumn;
use CoringaWc\FilamentActionApprovals\Support\DateDisplay;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('workbench::workbench.resources.expenses.columns.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('workbench::workbench.resources.expenses.columns.requester'))
                    ->sortable(),
                TextColumn::make('category')
                    ->label(__('workbench::workbench.resources.expenses.columns.category'))
                    ->formatStateUsing(fn (?string $state): string => blank($state) ? '-' : __('workbench::workbench.resources.expenses.categories.'.$state))
                    ->badge(),
                TextColumn::make('amount')
                    ->label(__('workbench::workbench.resources.expenses.columns.amount'))
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('workbench::workbench.resources.expenses.columns.status'))
                    ->formatStateUsing(fn (?string $state): string => blank($state) ? '-' : __('workbench::workbench.resources.expenses.statuses.'.$state))
                    ->badge(),
                ApprovalStatusColumn::make(),
                DateDisplay::column(
                    TextColumn::make('created_at')
                        ->label(__('workbench::workbench.resources.expenses.columns.created_at')),
                )->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
