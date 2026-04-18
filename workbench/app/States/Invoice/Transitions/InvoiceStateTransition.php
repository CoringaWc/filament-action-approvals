<?php

declare(strict_types=1);

namespace Workbench\App\States\Invoice\Transitions;

use Spatie\ModelStates\Transition;
use Workbench\App\Models\Invoice;
use Workbench\App\States\Invoice\InvoiceState;

abstract class InvoiceStateTransition extends Transition
{
    public function __construct(protected Invoice $invoice) {}

    /**
     * @return class-string<InvoiceState>
     */
    abstract protected function targetStateClass(): string;

    /**
     * @return array<string, mixed>
     */
    protected function extraAttributes(): array
    {
        return [];
    }

    public function handle(): Invoice
    {
        $currentState = $this->invoice->status;

        $this->invoice->fill(array_merge([
            'previous_status' => $currentState::getMorphClass(),
        ], $this->extraAttributes()));

        $targetStateClass = $this->targetStateClass();
        $this->invoice->status = new $targetStateClass($this->invoice);
        $this->invoice->save();

        return $this->invoice->refresh();
    }
}
