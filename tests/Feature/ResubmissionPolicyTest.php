<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Tests\Feature;

use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Tests\TestCase;
use Workbench\App\Models\Expense;
use Workbench\App\Models\PurchaseOrder;

class ResubmissionPolicyTest extends TestCase
{
    private ApprovalEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = $this->app->make(ApprovalEngine::class);
    }

    public function test_default_model_allows_resubmission(): void
    {
        $approver = $this->createUser();
        $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
        $order = PurchaseOrder::factory()->create();

        // Submit and reject
        $approval = $this->engine->submit($order, $flow, $approver->getKey());
        $step = $approval->currentStepInstance();
        $this->assertNotNull($step);
        $this->engine->reject($step, $approver->getKey(), 'Rejected');

        $order->refresh();
        $this->assertTrue($order->canBeSubmittedForApproval());
    }

    public function test_expense_blocks_resubmission_after_approval(): void
    {
        $approver = $this->createUser();
        $flow = $this->createSingleStepFlow(Expense::class, [$approver->getKey()]);
        $expense = Expense::factory()->create();

        // Submit and approve
        $approval = $this->engine->submit($expense, $flow, $approver->getKey());
        $step = $approval->currentStepInstance();
        $this->assertNotNull($step);
        $this->engine->approve($step, $approver->getKey());

        $expense->refresh();
        $this->assertFalse($expense->canBeSubmittedForApproval());
    }

    public function test_expense_allows_resubmission_after_rejection(): void
    {
        $approver = $this->createUser();
        $flow = $this->createSingleStepFlow(Expense::class, [$approver->getKey()]);
        $expense = Expense::factory()->create();

        // Submit and reject
        $approval = $this->engine->submit($expense, $flow, $approver->getKey());
        $step = $approval->currentStepInstance();
        $this->assertNotNull($step);
        $this->engine->reject($step, $approver->getKey(), 'Invalid receipt');

        $expense->refresh();
        $this->assertTrue($expense->canBeSubmittedForApproval());
    }
}
