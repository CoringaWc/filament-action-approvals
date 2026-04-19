<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Workbench\App\Models\Invoice;
use Workbench\App\States\Invoice\Issuing;
use Workbench\App\States\Invoice\Sent;

it('exposes transition actions from the state machine', function (): void {
    $actions = Invoice::approvableActions();
    $sendActionKey = Invoice::stateApprovalActionKey(Issuing::class, Sent::class);

    expect($actions)
        ->toHaveKey($sendActionKey)
        ->and($actions[$sendActionKey])->toBe('Em emissão -> Enviada');
});

it('parses legacy state action keys for backward compatibility', function (): void {
    $parseStateApprovalActionKey = new ReflectionMethod(Invoice::class, 'parseStateApprovalActionKey');
    $parseStateApprovalActionKey->setAccessible(true);

    /** @var array{0: class-string, 1: class-string} $parsedStates */
    $parsedStates = $parseStateApprovalActionKey->invoke(null, 'Issuing-Sent');

    expect($parsedStates[0])->toBe(Issuing::class)
        ->and($parsedStates[1])->toBe(Sent::class);
});

it('executes transition immediately when no action-specific flow exists', function (): void {
    $invoice = Invoice::factory()->issuing()->create();

    $result = $invoice->transitionWithApproval('status', Sent::class);

    expect($result->executed)->toBeTrue()
        ->and($result->pendingApproval)->toBeFalse();

    $invoice->refresh();

    expect($invoice->status)->toBeInstanceOf(Sent::class)
        ->and($invoice->previous_status)->toBe(Issuing::getMorphClass())
        ->and($invoice->sent_at)->not->toBeNull();
});

it('submits for approval and executes after approval', function (): void {
    $requester = $this->createUser();
    $approver = $this->createUser();
    $invoice = Invoice::factory()->for($requester)->issuing()->create();
    $actionKey = Invoice::stateApprovalActionKey(Issuing::class, Sent::class);
    $engine = app(ApprovalEngine::class);

    $this->createSingleStepFlow(Invoice::class, [$approver->getKey()], $actionKey);

    $result = $invoice->transitionWithApproval('status', Sent::class, submittedBy: $requester->getKey());

    expect($result->executed)->toBeFalse()
        ->and($result->pendingApproval)->toBeTrue()
        ->and($result->approval)->toBeInstanceOf(Approval::class);

    $invoice->refresh();
    expect($invoice->status)->toBeInstanceOf(Issuing::class);

    $stepInstance = $result->approval?->currentStepInstance();
    expect($stepInstance)->not->toBeNull();

    $engine->approve($stepInstance, $approver->getKey(), 'Approved for sending');

    $invoice->refresh();
    $result->approval?->refresh();

    expect($invoice->status)->toBeInstanceOf(Sent::class)
        ->and($invoice->previous_status)->toBe(Issuing::getMorphClass())
        ->and($invoice->sent_at)->not->toBeNull()
        ->and($result->approval)->not->toBeNull()
        ->and($result->approval->isPending())->toBeFalse();
});
