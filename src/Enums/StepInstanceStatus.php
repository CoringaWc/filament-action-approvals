<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StepInstanceStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Waiting = 'waiting';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Skipped = 'skipped';

    public function getLabel(): string
    {
        return __('filament-action-approvals::approval.step_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Waiting => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Skipped => 'gray',
        };
    }
}
