<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Expenses\Pages;

use Filament\Resources\Pages\CreateRecord;
use Workbench\App\Filament\Resources\Expenses\ExpenseResource;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;
}
