<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ExpenseApprovableAction: string implements HasColor, HasIcon, HasLabel
{
    case Submit = 'submit';
    case Reimburse = 'reimburse';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submit => 'Submit for Approval',
            self::Reimburse => 'Request Reimbursement',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Submit => 'primary',
            self::Reimburse => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Submit => 'heroicon-o-paper-airplane',
            self::Reimburse => 'heroicon-o-currency-dollar',
        };
    }
}
