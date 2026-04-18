<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Tests\Feature;

use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Tests\TestCase;
use ReflectionMethod;
use Workbench\App\Models\Invoice;
use Workbench\App\States\Invoice\Issuing;
use Workbench\App\States\Invoice\Sent;

class StateApprovalsTest extends TestCase
{
    public function test_stateful_models_expose_transition_actions_from_the_state_machine(): void
    {
        $actions = Invoice::approvableActions();
        $sendActionKey = Invoice::stateApprovalActionKey(Issuing::class, Sent::class);

        $this->assertArrayHasKey($sendActionKey, $actions);
        $this->assertSame('Em emissão -> Enviada', $actions[$sendActionKey]);
    }

    public function test_legacy_state_action_keys_are_parsed_for_backward_compatibility(): void
    {
        $parseStateApprovalActionKey = new ReflectionMethod(Invoice::class, 'parseStateApprovalActionKey');
        $parseStateApprovalActionKey->setAccessible(true);

        /** @var array{0: class-string, 1: class-string} $parsedStates */
        $parsedStates = $parseStateApprovalActionKey->invoke(null, 'Issuing-Sent');

        $this->assertSame(Issuing::class, $parsedStates[0]);
        $this->assertSame(Sent::class, $parsedStates[1]);
    }

    public function test_transition_executes_immediately_when_no_action_specific_flow_exists(): void
    {
        $invoice = Invoice::factory()->issuing()->create();

        $result = $invoice->transitionWithApproval('status', Sent::class);

        $this->assertTrue($result->executed);
        $this->assertFalse($result->pendingApproval);

        $invoice->refresh();

        $this->assertInstanceOf(Sent::class, $invoice->status);
        $this->assertSame(Issuing::getMorphClass(), $invoice->previous_status);
        $this->assertNotNull($invoice->sent_at);
    }

    public function test_transition_submits_for_approval_and_executes_after_approval(): void
    {
        $requester = $this->createUser();
        $approver = $this->createUser();
        $invoice = Invoice::factory()->for($requester)->issuing()->create();
        $actionKey = Invoice::stateApprovalActionKey(Issuing::class, Sent::class);
        $engine = $this->app->make(ApprovalEngine::class);

        $this->createSingleStepFlow(Invoice::class, [$approver->getKey()], $actionKey);

        $result = $invoice->transitionWithApproval('status', Sent::class, submittedBy: $requester->getKey());

        $this->assertFalse($result->executed);
        $this->assertTrue($result->pendingApproval);
        $this->assertInstanceOf(Approval::class, $result->approval);

        $invoice->refresh();
        $this->assertInstanceOf(Issuing::class, $invoice->status);

        $stepInstance = $result->approval?->currentStepInstance();

        $this->assertNotNull($stepInstance);

        $engine->approve($stepInstance, $approver->getKey(), 'Approved for sending');

        $invoice->refresh();
        $result->approval?->refresh();

        $this->assertInstanceOf(Sent::class, $invoice->status);
        $this->assertSame(Issuing::getMorphClass(), $invoice->previous_status);
        $this->assertNotNull($invoice->sent_at);
        $this->assertNotNull($result->approval);
        $this->assertFalse($result->approval->isPending());
    }
}
