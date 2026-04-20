<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum StepInstanceStatus: string implements HasColor, HasIcon, HasLabel
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

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedMinusCircle,
            self::Waiting => Heroicon::OutlinedClock,
            self::Approved => Heroicon::OutlinedCheckCircle,
            self::Rejected => Heroicon::OutlinedXCircle,
            self::Skipped => Heroicon::OutlinedArrowUturnLeft,
        };
    }
}
