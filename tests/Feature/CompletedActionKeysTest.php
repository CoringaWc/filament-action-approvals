<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Workbench\App\Models\PurchaseOrder;

beforeEach(function (): void {
    $this->engine = app(ApprovalEngine::class);
});

it('returns empty when no approvals exist', function (): void {
    $order = PurchaseOrder::factory()->create();

    expect(Approval::completedActionKeysFor($order))->toBe([]);
});

it('returns empty when only pending approvals exist', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()], 'submit');
    $order = PurchaseOrder::factory()->create();

    $this->engine->submit($order, $flow, $approver->getKey(), 'submit');

    expect(Approval::completedActionKeysFor($order))->toBe([]);
});

it('returns action key when approval is approved', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()], 'submit');
    $order = PurchaseOrder::factory()->create();

    $approval = $this->engine->submit($order, $flow, $approver->getKey(), 'submit');
    $step = $approval->currentStepInstance();
    expect($step)->not->toBeNull();
    $this->engine->approve($step, $approver->getKey());

    expect(Approval::completedActionKeysFor($order))->toContain('submit');
});

it('returns action key when approval is rejected', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()], 'submit');
    $order = PurchaseOrder::factory()->create();

    $approval = $this->engine->submit($order, $flow, $approver->getKey(), 'submit');
    $step = $approval->currentStepInstance();
    expect($step)->not->toBeNull();
    $this->engine->reject($step, $approver->getKey(), 'No');

    expect(Approval::completedActionKeysFor($order))->toContain('submit');
});

it('does not return action key for cancelled approvals', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()], 'submit');
    $order = PurchaseOrder::factory()->create();

    $approval = $this->engine->submit($order, $flow, $approver->getKey(), 'submit');
    $this->engine->cancel($approval);

    expect(Approval::completedActionKeysFor($order))->toBe([]);
});

it('returns multiple distinct completed action keys', function (): void {
    $approver = $this->createUser();
    $submitFlow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()], 'submit');
    $cancelFlow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()], 'cancel');
    $order = PurchaseOrder::factory()->create();

    $approval1 = $this->engine->submit($order, $submitFlow, $approver->getKey(), 'submit');
    $step1 = $approval1->currentStepInstance();
    expect($step1)->not->toBeNull();
    $this->engine->approve($step1, $approver->getKey());

    $approval2 = $this->engine->submit($order, $cancelFlow, $approver->getKey(), 'cancel');
    $step2 = $approval2->currentStepInstance();
    expect($step2)->not->toBeNull();
    $this->engine->reject($step2, $approver->getKey(), 'No');

    $completedKeys = Approval::completedActionKeysFor($order);
    expect($completedKeys)->toContain('submit')
        ->toContain('cancel')
        ->toHaveCount(2);
});

it('scopes completed keys to the specific model instance', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()], 'submit');

    $order1 = PurchaseOrder::factory()->create();
    $order2 = PurchaseOrder::factory()->create();

    $approval = $this->engine->submit($order1, $flow, $approver->getKey(), 'submit');
    $step = $approval->currentStepInstance();
    expect($step)->not->toBeNull();
    $this->engine->approve($step, $approver->getKey());

    expect(Approval::completedActionKeysFor($order1))->toContain('submit')
        ->and(Approval::completedActionKeysFor($order2))->toBe([]);
});
