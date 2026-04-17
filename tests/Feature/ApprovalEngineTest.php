<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Tests\Feature;

use CoringaWc\FilamentActionApprovals\Enums\ActionType;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Enums\StepInstanceStatus;
use CoringaWc\FilamentActionApprovals\Events\ApprovalRejected;
use CoringaWc\FilamentActionApprovals\Events\ApprovalSubmitted;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Workbench\App\Models\PurchaseOrder;

class ApprovalEngineTest extends TestCase
{
    private ApprovalEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = $this->app->make(ApprovalEngine::class);
    }

    // ─── Submit ───────────────────────────────────────────────────

    public function test_submit_creates_approval_with_pending_status(): void
    {
        $approver = $this->createUser();
        $requester = $this->createUser();
        $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
        $order = PurchaseOrder::factory()->for($requester)->create();

        $approval = $this->engine->submit($order, $flow, $requester->getKey());

        $this->assertInstanceOf(Approval::class, $approval);
        $this->assertEquals(ApprovalStatus::Pending, $approval->status);
        $this->assertEquals($requester->getKey(), $approval->submitted_by);
        $this->assertEquals($order->getKey(), $approval->approvable_id);
    }

    public function test_submit_creates_step_instances(): void
    {
        $approver = $this->createUser();
        $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
        $order = PurchaseOrder::factory()->create();

        $approval = $this->engine->submit($order, $flow, $approver->getKey());

        $this->assertCount(1, $approval->stepInstances);

        $step = $approval->stepInstances->first();
        $this->assertEquals(StepInstanceStatus::Waiting, $step->status);
        $this->assertContains($approver->getKey(), $step->assigned_approver_ids);
    }

    public function test_submit_records_submitted_action(): void
    {
        $approver = $this->createUser();
        $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
        $order = PurchaseOrder::factory()->create();

        $approval = $this->engine->submit($order, $flow, $approver->getKey());

        $action = $approval->actions()->first();
        $this->assertNotNull($action);
        $this->assertEquals(ActionType::Submitted, $action->type);
    }

    public function test_submit_fires_approval_submitted_event(): void
    {
        Event::fake();

        $approver = $this->createUser();
        $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
        $order = PurchaseOrder::factory()->create();

        $this->engine->submit($order, $flow, $approver->getKey());

        Event::assertDispatched(ApprovalSubmitted::class);
    }

    // ─── Approve ──────────────────────────────────────────────────

    public function test_approve_single_step_completes_approval(): void
    {
        $approver = $this->createUser();
        $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
        $order = PurchaseOrder::factory()->create();

        $approval = $this->engine->submit($order, $flow, $approver->getKey());
        $stepInstance = $approval->currentStepInstance();
        $this->assertNotNull($stepInstance);

        $this->engine->approve($stepInstance, $approver->getKey(), 'Looks good');

        $approval->refresh();
        $this->assertEquals(ApprovalStatus::Approved, $approval->status);
        $this->assertNotNull($approval->completed_at);
    }

    public function test_approve_triggers_model_callback(): void
    {
        $approver = $this->createUser();
        $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
        $order = PurchaseOrder::factory()->create(['status' => 'draft']);

        $approval = $this->engine->submit($order, $flow, $approver->getKey());
        $stepInstance = $approval->currentStepInstance();
        $this->assertNotNull($stepInstance);

        $this->engine->approve($stepInstance, $approver->getKey());

        $order->refresh();
        $this->assertEquals('approved', $order->status);
    }

    public function test_approve_multi_step_advances_to_next_step(): void
    {
        $manager = $this->createUser();
        $director = $this->createUser();

        $flow = $this->createMultiStepFlow(PurchaseOrder::class, [
            ['name' => 'Manager', 'approver_ids' => [$manager->getKey()]],
            ['name' => 'Director', 'approver_ids' => [$director->getKey()]],
        ]);

        $order = PurchaseOrder::factory()->create();
        $approval = $this->engine->submit($order, $flow, $manager->getKey());

        // Approve step 1
        $step1 = $approval->currentStepInstance();
        $this->assertNotNull($step1);
        $this->engine->approve($step1, $manager->getKey());

        $approval->refresh();
        $this->assertEquals(ApprovalStatus::Pending, $approval->status);

        // Step 2 should now be waiting
        $step2 = $approval->currentStepInstance();
        $this->assertNotNull($step2);
        $this->assertEquals(StepInstanceStatus::Waiting, $step2->status);
        $this->assertContains($director->getKey(), $step2->assigned_approver_ids);
    }

    public function test_approve_last_step_completes_approval(): void
    {
        $manager = $this->createUser();
        $director = $this->createUser();

        $flow = $this->createMultiStepFlow(PurchaseOrder::class, [
            ['name' => 'Manager', 'approver_ids' => [$manager->getKey()]],
            ['name' => 'Director', 'approver_ids' => [$director->getKey()]],
        ]);

        $order = PurchaseOrder::factory()->create(['status' => 'draft']);
        $approval = $this->engine->submit($order, $flow, $manager->getKey());

        // Approve step 1
        $step1 = $approval->currentStepInstance();
        $this->assertNotNull($step1);
        $this->engine->approve($step1, $manager->getKey());

        // Approve step 2
        $approval->refresh();
        $step2 = $approval->currentStepInstance();
        $this->assertNotNull($step2);
        $this->engine->approve($step2, $director->getKey());

        $approval->refresh();
        $this->assertEquals(ApprovalStatus::Approved, $approval->status);

        $order->refresh();
        $this->assertEquals('approved', $order->status);
    }

    // ─── Reject ───────────────────────────────────────────────────

    public function test_reject_marks_approval_as_rejected(): void
    {
        $approver = $this->createUser();
        $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
        $order = PurchaseOrder::factory()->create(['status' => 'draft']);

        $approval = $this->engine->submit($order, $flow, $approver->getKey());
        $stepInstance = $approval->currentStepInstance();
        $this->assertNotNull($stepInstance);

        $this->engine->reject($stepInstance, $approver->getKey(), 'Budget exceeded');

        $approval->refresh();
        $this->assertEquals(ApprovalStatus::Rejected, $approval->status);
        $this->assertNotNull($approval->completed_at);

        $order->refresh();
        $this->assertEquals('rejected', $order->status);
    }

    public function test_reject_skips_remaining_steps(): void
    {
        $manager = $this->createUser();
        $director = $this->createUser();

        $flow = $this->createMultiStepFlow(PurchaseOrder::class, [
            ['name' => 'Manager', 'approver_ids' => [$manager->getKey()]],
            ['name' => 'Director', 'approver_ids' => [$director->getKey()]],
        ]);

        $order = PurchaseOrder::factory()->create();
        $approval = $this->engine->submit($order, $flow, $manager->getKey());

        $step1 = $approval->currentStepInstance();
        $this->assertNotNull($step1);

        $this->engine->reject($step1, $manager->getKey(), 'Invalid');

        $approval->refresh();
        $pendingSteps = $approval->stepInstances()
            ->where('status', StepInstanceStatus::Pending)
            ->count();

        $this->assertEquals(0, $pendingSteps);

        $skippedSteps = $approval->stepInstances()
            ->where('status', StepInstanceStatus::Skipped)
            ->count();

        $this->assertEquals(1, $skippedSteps);
    }

    public function test_reject_fires_approval_rejected_event(): void
    {
        Event::fake();

        $approver = $this->createUser();
        $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
        $order = PurchaseOrder::factory()->create();

        $approval = $this->engine->submit($order, $flow, $approver->getKey());
        $stepInstance = $approval->currentStepInstance();
        $this->assertNotNull($stepInstance);

        $this->engine->reject($stepInstance, $approver->getKey(), 'No');

        Event::assertDispatched(ApprovalRejected::class);
    }

    // ─── Comment ──────────────────────────────────────────────────

    public function test_comment_records_action(): void
    {
        $approver = $this->createUser();
        $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
        $order = PurchaseOrder::factory()->create();

        $approval = $this->engine->submit($order, $flow, $approver->getKey());

        $this->engine->comment($approval, $approver->getKey(), 'Need more details');

        $commentAction = $approval->actions()
            ->where('type', ActionType::Commented)
            ->first();

        $this->assertNotNull($commentAction);
        $this->assertEquals('Need more details', $commentAction->comment);
    }

    // ─── Delegate ─────────────────────────────────────────────────

    public function test_delegate_creates_delegation_record(): void
    {
        $approver = $this->createUser();
        $delegate = $this->createUser();
        $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
        $order = PurchaseOrder::factory()->create();

        $approval = $this->engine->submit($order, $flow, $approver->getKey());
        $stepInstance = $approval->currentStepInstance();
        $this->assertNotNull($stepInstance);

        $this->engine->delegate($stepInstance, $approver->getKey(), $delegate->getKey(), 'On vacation');

        $delegation = $stepInstance->delegations()->first();
        $this->assertNotNull($delegation);
        $this->assertEquals($approver->getKey(), $delegation->from_user_id);
        $this->assertEquals($delegate->getKey(), $delegation->to_user_id);
        $this->assertEquals('On vacation', $delegation->reason);
    }

    public function test_delegated_user_can_act_on_step(): void
    {
        $approver = $this->createUser();
        $delegate = $this->createUser();
        $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
        $order = PurchaseOrder::factory()->create();

        $approval = $this->engine->submit($order, $flow, $approver->getKey());
        $stepInstance = $approval->currentStepInstance();
        $this->assertNotNull($stepInstance);

        $this->engine->delegate($stepInstance, $approver->getKey(), $delegate->getKey());

        $this->assertTrue($stepInstance->canUserAct($delegate->getKey()));
    }

    public function test_delegate_can_approve_step(): void
    {
        $approver = $this->createUser();
        $delegate = $this->createUser();
        $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
        $order = PurchaseOrder::factory()->create(['status' => 'draft']);

        $approval = $this->engine->submit($order, $flow, $approver->getKey());
        $stepInstance = $approval->currentStepInstance();
        $this->assertNotNull($stepInstance);

        $this->engine->delegate($stepInstance, $approver->getKey(), $delegate->getKey());
        $this->engine->approve($stepInstance, $delegate->getKey());

        $approval->refresh();
        $this->assertEquals(ApprovalStatus::Approved, $approval->status);

        $order->refresh();
        $this->assertEquals('approved', $order->status);
    }

    // ─── Cancel ───────────────────────────────────────────────────

    public function test_cancel_marks_approval_as_cancelled(): void
    {
        $approver = $this->createUser();
        $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
        $order = PurchaseOrder::factory()->create();

        $approval = $this->engine->submit($order, $flow, $approver->getKey());

        $this->engine->cancel($approval);

        $approval->refresh();
        $this->assertEquals(ApprovalStatus::Cancelled, $approval->status);
    }

    public function test_cancel_skips_all_pending_steps(): void
    {
        $manager = $this->createUser();
        $director = $this->createUser();

        $flow = $this->createMultiStepFlow(PurchaseOrder::class, [
            ['name' => 'Manager', 'approver_ids' => [$manager->getKey()]],
            ['name' => 'Director', 'approver_ids' => [$director->getKey()]],
        ]);

        $order = PurchaseOrder::factory()->create();
        $approval = $this->engine->submit($order, $flow, $manager->getKey());

        $this->engine->cancel($approval);

        $approval->refresh();
        $activeSteps = $approval->stepInstances()
            ->whereIn('status', [StepInstanceStatus::Pending, StepInstanceStatus::Waiting])
            ->count();

        $this->assertEquals(0, $activeSteps);
    }

    // ─── HasApprovals trait ───────────────────────────────────────

    public function test_model_has_approvals_relationship(): void
    {
        $approver = $this->createUser();
        $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
        $order = PurchaseOrder::factory()->create();

        $order->submitForApproval($flow, $approver->getKey());

        $this->assertCount(1, $order->approvals);
        $this->assertTrue($order->isPendingApproval());
        $this->assertFalse($order->isApproved());
    }

    public function test_model_reports_correct_approval_status(): void
    {
        $approver = $this->createUser();
        $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
        $order = PurchaseOrder::factory()->create();

        $approval = $order->submitForApproval($flow, $approver->getKey());

        $this->assertEquals(ApprovalStatus::Pending, $order->approvalStatus());
        $this->assertTrue($order->isPendingApproval());
        $this->assertFalse($order->isApproved());
        $this->assertFalse($order->isRejected());

        $stepInstance = $approval->currentStepInstance();
        $this->assertNotNull($stepInstance);
        $this->engine->approve($stepInstance, $approver->getKey());

        $order->refresh();
        $this->assertEquals(ApprovalStatus::Approved, $order->approvalStatus());
        $this->assertTrue($order->isApproved());
        $this->assertFalse($order->isPendingApproval());
    }

    public function test_cannot_submit_while_pending(): void
    {
        $approver = $this->createUser();
        $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
        $order = PurchaseOrder::factory()->create();

        $order->submitForApproval($flow, $approver->getKey());

        $this->assertFalse($order->canBeSubmittedForApproval());
    }
}
