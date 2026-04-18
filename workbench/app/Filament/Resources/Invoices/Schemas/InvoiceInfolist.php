<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Invoices\Schemas;

use CoringaWc\FilamentActionApprovals\Support\DateDisplay;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Workbench\App\Models\Invoice;
use Workbench\App\States\Invoice\InvoiceState;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('workbench::workbench.resources.invoices.sections.overview'))
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('number')
                        ->label(__('workbench::workbench.resources.invoices.fields.number')),
                    TextEntry::make('title')
                        ->label(__('workbench::workbench.resources.invoices.fields.title')),
                    TextEntry::make('user.name')
                        ->label(__('workbench::workbench.resources.invoices.fields.requester')),
                    TextEntry::make('amount')
                        ->label(__('workbench::workbench.resources.invoices.fields.amount'))
                        ->money('BRL'),
                    TextEntry::make('status')
                        ->label(__('workbench::workbench.resources.invoices.fields.status'))
                        ->badge()
                        ->formatStateUsing(fn (InvoiceState $state): string => $state->getLabel())
                        ->color(fn (InvoiceState $state): string => $state->getColor()),
                    TextEntry::make('previous_status')
                        ->label(__('workbench::workbench.resources.invoices.fields.previous_status'))
                        ->state(fn (Invoice $record): string => $record->getPreviousStatusEnum()?->getLabel() ?? '-'),
                    DateDisplay::entry(
                        TextEntry::make('sent_at')
                            ->label(__('workbench::workbench.resources.invoices.fields.sent_at'))
                            ->placeholder('-'),
                    ),
                    DateDisplay::entry(
                        TextEntry::make('paid_at')
                            ->label(__('workbench::workbench.resources.invoices.fields.paid_at'))
                            ->placeholder('-'),
                    ),
                    DateDisplay::entry(
                        TextEntry::make('cancelled_at')
                            ->label(__('workbench::workbench.resources.invoices.fields.cancelled_at'))
                            ->placeholder('-'),
                    ),
                    DateDisplay::entry(
                        TextEntry::make('created_at')
                            ->label(__('workbench::workbench.resources.invoices.fields.created_at'))
                            ->placeholder('-'),
                    ),
                ]),
        ]);
    }
}
