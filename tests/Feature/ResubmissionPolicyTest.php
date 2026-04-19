<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Workbench\App\Models\Expense;
use Workbench\App\Models\PurchaseOrder;

beforeEach(function (): void {
    $this->engine = app(ApprovalEngine::class);
});

it('allows resubmission by default', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create();

    $approval = $this->engine->submit($order, $flow, $approver->getKey());
    $step = $approval->currentStepInstance();
    expect($step)->not->toBeNull();
    $this->engine->reject($step, $approver->getKey(), 'Rejected');

    $order->refresh();
    expect($order->canBeSubmittedForApproval())->toBeTrue();
});

it('blocks expense resubmission after approval', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(Expense::class, [$approver->getKey()]);
    $expense = Expense::factory()->create();

    $approval = $this->engine->submit($expense, $flow, $approver->getKey());
    $step = $approval->currentStepInstance();
    expect($step)->not->toBeNull();
    $this->engine->approve($step, $approver->getKey());

    $expense->refresh();
    expect($expense->canBeSubmittedForApproval())->toBeFalse();
});

it('allows expense resubmission after rejection', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(Expense::class, [$approver->getKey()]);
    $expense = Expense::factory()->create();

    $approval = $this->engine->submit($expense, $flow, $approver->getKey());
    $step = $approval->currentStepInstance();
    expect($step)->not->toBeNull();
    $this->engine->reject($step, $approver->getKey(), 'Invalid receipt');

    $expense->refresh();
    expect($expense->canBeSubmittedForApproval())->toBeTrue();
});
