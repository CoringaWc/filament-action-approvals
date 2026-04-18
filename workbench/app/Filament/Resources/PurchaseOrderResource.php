<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources;

use CoringaWc\FilamentAcl\Attributes\PermissionSubject;
use CoringaWc\FilamentAcl\Resources\Concerns\HasResourcePermissions;
use CoringaWc\FilamentActionApprovals\Columns\ApprovalStatusColumn;
use CoringaWc\FilamentActionApprovals\Concerns\HasApprovalsResource;
use CoringaWc\FilamentActionApprovals\RelationManagers\ApprovalsRelationManager;
use CoringaWc\FilamentActionApprovals\Support\DateDisplay;
use CoringaWc\FilamentActionApprovals\Support\TranslatableSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;

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

    public static function table(Table $table): Table
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
                )
                    ->sortable(),
            ]);
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
            'index' => PurchaseOrderResource\Pages\ListPurchaseOrders::route('/'),
            'create' => PurchaseOrderResource\Pages\CreatePurchaseOrder::route('/create'),
            'edit' => PurchaseOrderResource\Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
