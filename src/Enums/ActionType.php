<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ActionType: string implements HasColor, HasIcon, HasLabel
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

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Submitted => Heroicon::OutlinedPaperAirplane,
            self::Approved => Heroicon::OutlinedCheckCircle,
            self::Rejected => Heroicon::OutlinedXCircle,
            self::Commented => Heroicon::OutlinedChatBubbleLeftRight,
            self::Delegated => Heroicon::OutlinedArrowRightCircle,
            self::Escalated => Heroicon::OutlinedExclamationTriangle,
            self::Returned => Heroicon::OutlinedArrowUturnLeft,
        };
    }
}
