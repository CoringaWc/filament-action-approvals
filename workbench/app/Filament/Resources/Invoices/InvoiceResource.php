<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Invoices;

use CoringaWc\FilamentAcl\Attributes\PermissionSubject;
use CoringaWc\FilamentAcl\Resources\Concerns\HasResourcePermissions;
use CoringaWc\FilamentActionApprovals\RelationManagers\ApprovalsRelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;
use Workbench\App\Filament\Resources\Invoices\Pages\CreateInvoice;
use Workbench\App\Filament\Resources\Invoices\Pages\EditInvoice;
use Workbench\App\Filament\Resources\Invoices\Pages\ListInvoices;
use Workbench\App\Filament\Resources\Invoices\Pages\ViewInvoice;
use Workbench\App\Filament\Resources\Invoices\Schemas\InvoiceForm;
use Workbench\App\Filament\Resources\Invoices\Schemas\InvoiceInfolist;
use Workbench\App\Filament\Resources\Invoices\Tables\InvoicesTable;
use Workbench\App\Models\Invoice;

#[PermissionSubject('invoice')]
class InvoiceResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Invoice::class;

    public static function getModelLabel(): string
    {
        return __('workbench::workbench.resources.invoices.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('workbench::workbench.resources.invoices.plural_model_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('workbench::workbench.resources.invoices.navigation_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('workbench::workbench.resources.invoices.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ApprovalsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'view' => ViewInvoice::route('/{record}'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }
}
