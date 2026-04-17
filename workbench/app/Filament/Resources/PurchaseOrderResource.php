<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources;

use CoringaWc\FilamentAcl\Attributes\PermissionSubject;
use CoringaWc\FilamentAcl\Resources\Concerns\HasResourcePermissions;
use CoringaWc\FilamentActionApprovals\Columns\ApprovalStatusColumn;
use CoringaWc\FilamentActionApprovals\Concerns\HasApprovalsResource;
use CoringaWc\FilamentActionApprovals\RelationManagers\ApprovalsRelationManager;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;
use Workbench\App\Models\PurchaseOrder;

#[PermissionSubject('purchase-order')]
class PurchaseOrderResource extends Resource
{
    use HasApprovalsResource;
    use HasResourcePermissions;

    protected static ?string $model = PurchaseOrder::class;

    protected static string|UnitEnum|null $navigationGroup = 'Procurement';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->required()
                ->maxLength(255),

            Textarea::make('description')
                ->rows(3),

            TextInput::make('amount')
                ->numeric()
                ->prefix('$')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Requester')
                    ->sortable(),

                TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),

                ApprovalStatusColumn::make(),

                TextColumn::make('created_at')
                    ->dateTime()
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
