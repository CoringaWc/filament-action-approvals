<?php

namespace CoringaWc\FilamentActionApprovals\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ActionType: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Commented = 'commented';
    case Delegated = 'delegated';
    case Escalated = 'escalated';
    case Returned = 'returned';

    public function getLabel(): string
    {
        return __('filament-action-approvals::approval.action_type.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Submitted => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Commented => 'gray',
            self::Delegated => 'warning',
            self::Escalated => 'danger',
            self::Returned => 'warning',
        };
    }
}
