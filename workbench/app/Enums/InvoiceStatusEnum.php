<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum InvoiceStatusEnum: string implements HasColor, HasIcon, HasLabel
{
    case Issuing = 'Issuing';
    case Sent = 'Sent';
    case AwaitingPayment = 'AwaitingPayment';
    case Paid = 'Paid';
    case Cancelled = 'Cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Issuing => __('workbench::workbench.resources.invoices.states.issuing'),
            self::Sent => __('workbench::workbench.resources.invoices.states.sent'),
            self::AwaitingPayment => __('workbench::workbench.resources.invoices.states.awaiting_payment'),
            self::Paid => __('workbench::workbench.resources.invoices.states.paid'),
            self::Cancelled => __('workbench::workbench.resources.invoices.states.cancelled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Issuing => 'warning',
            self::Sent => 'info',
            self::AwaitingPayment => 'warning',
            self::Paid => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Issuing => 'heroicon-o-document-text',
            self::Sent => 'heroicon-o-paper-airplane',
            self::AwaitingPayment => 'heroicon-o-clock',
            self::Paid => 'heroicon-o-check-circle',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }
}
