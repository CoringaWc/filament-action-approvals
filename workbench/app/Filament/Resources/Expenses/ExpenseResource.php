<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Expenses;

use CoringaWc\FilamentAcl\Attributes\PermissionSubject;
use CoringaWc\FilamentAcl\Resources\Concerns\HasResourcePermissions;
use CoringaWc\FilamentActionApprovals\Concerns\HasApprovalsResource;
use CoringaWc\FilamentActionApprovals\RelationManagers\ApprovalsRelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;
use Workbench\App\Filament\Resources\Expenses\Pages\CreateExpense;
use Workbench\App\Filament\Resources\Expenses\Pages\EditExpense;
use Workbench\App\Filament\Resources\Expenses\Pages\ListExpenses;
use Workbench\App\Filament\Resources\Expenses\Schemas\ExpenseForm;
use Workbench\App\Filament\Resources\Expenses\Tables\ExpensesTable;
use Workbench\App\Models\Expense;

#[PermissionSubject('expense')]
class ExpenseResource extends Resource
{
    use HasApprovalsResource;
    use HasResourcePermissions;

    protected static ?string $model = Expense::class;

    public static function getModelLabel(): string
    {
        return __('workbench::workbench.resources.expenses.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('workbench::workbench.resources.expenses.plural_model_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('workbench::workbench.resources.expenses.navigation_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('workbench::workbench.resources.expenses.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return ExpenseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpensesTable::configure($table);
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
            'index' => ListExpenses::route('/'),
            'create' => CreateExpense::route('/create'),
            'edit' => EditExpense::route('/{record}/edit'),
        ];
    }
}
