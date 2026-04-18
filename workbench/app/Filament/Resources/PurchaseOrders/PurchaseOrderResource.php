<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\PurchaseOrders;

use CoringaWc\FilamentAcl\Attributes\PermissionSubject;
use CoringaWc\FilamentAcl\Resources\Concerns\HasResourcePermissions;
use CoringaWc\FilamentActionApprovals\Concerns\HasApprovalsResource;
use CoringaWc\FilamentActionApprovals\RelationManagers\ApprovalsRelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;
use Workbench\App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use Workbench\App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use Workbench\App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use Workbench\App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use Workbench\App\Filament\Resources\PurchaseOrders\Tables\PurchaseOrdersTable;
use Workbench\App\Models\PurchaseOrder;

#[PermissionSubject('purchase-order')]
class PurchaseOrderResource extends Resource
{
    use HasApprovalsResource;
    use HasResourcePermissions;

    protected static ?string $model = PurchaseOrder::class;

    public static function getModelLabel(): string
    {
        return __('workbench::workbench.resources.purchase_orders.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('workbench::workbench.resources.purchase_orders.plural_model_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('workbench::workbench.resources.purchase_orders.navigation_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('workbench::workbench.resources.purchase_orders.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseOrdersTable::configure($table);
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
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
