<?php

declare(strict_types=1);

namespace Workbench\App\States\Invoice;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;
use Workbench\App\Enums\InvoiceStatusEnum;
use Workbench\App\Models\Invoice;
use Workbench\App\States\Invoice\Transitions\ToAwaitingPayment;
use Workbench\App\States\Invoice\Transitions\ToCancelled;
use Workbench\App\States\Invoice\Transitions\ToPaid;
use Workbench\App\States\Invoice\Transitions\ToSent;

/**
 * @extends State<Invoice>
 */
abstract class InvoiceState extends State implements HasColor, HasIcon, HasLabel
{
    abstract public function toEnum(): InvoiceStatusEnum;

    public function getLabel(): string
    {
        return $this->toEnum()->getLabel();
    }

    public function getColor(): string
    {
        return $this->toEnum()->getColor();
    }

    public function getIcon(): string
    {
        return $this->toEnum()->getIcon();
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Issuing::class)
            ->allowTransition(Issuing::class, Sent::class, ToSent::class)
            ->allowTransition(Issuing::class, Cancelled::class, ToCancelled::class)
            ->allowTransition(Sent::class, AwaitingPayment::class, ToAwaitingPayment::class)
            ->allowTransition(Sent::class, Cancelled::class, ToCancelled::class)
            ->allowTransition(AwaitingPayment::class, Paid::class, ToPaid::class)
            ->allowTransition(AwaitingPayment::class, Cancelled::class, ToCancelled::class);
    }
}
