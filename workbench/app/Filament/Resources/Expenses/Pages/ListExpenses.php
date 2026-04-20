<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Expenses\Pages;

use CoringaWc\FilamentActionApprovals\Actions\ListApprovalsAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Workbench\App\Filament\Resources\Expenses\ExpenseResource;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ListApprovalsAction::make()
                ->forApprovableType(ExpenseResource::getModel()),
        ];
    }
}
