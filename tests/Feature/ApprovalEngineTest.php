<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Enums\ActionType;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Enums\StepInstanceStatus;
use CoringaWc\FilamentActionApprovals\Events\ApprovalRejected;
use CoringaWc\FilamentActionApprovals\Events\ApprovalSubmitted;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Workbench\App\Models\PurchaseOrder;

beforeEach(function (): void {
    $this->engine = app(ApprovalEngine::class);
});

// ─── Submit ───────────────────────────────────────────────────

it('creates approval with pending status on submit', function (): void {
    $approver = $this->createUser();
    $requester = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->for($requester)->create();

    $approval = $this->engine->submit($order, $flow, $requester->getKey());

    expect($approval)
        ->toBeInstanceOf(Approval::class)
        ->status->toBe(ApprovalStatus::Pending)
        ->submitted_by->toBe($requester->getKey())
        ->approvable_id->toBe($order->getKey());
});

it('creates step instances on submit', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create();

    $approval = $this->engine->submit($order, $flow, $approver->getKey());

    expect($approval->stepInstances)->toHaveCount(1);

    $step = $approval->stepInstances->first();
    expect($step)
        ->status->toBe(StepInstanceStatus::Waiting)
        ->and($step->assigned_approver_ids)->toContain($approver->getKey());
});

it('records submitted action on submit', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create();

    $approval = $this->engine->submit($order, $flow, $approver->getKey());

    $action = $approval->actions()->first();
    expect($action)
        ->not->toBeNull()
        ->type->toBe(ActionType::Submitted);
});

it('fires approval submitted event', function (): void {
    Event::fake();

    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create();

    $this->engine->submit($order, $flow, $approver->getKey());

    Event::assertDispatched(ApprovalSubmitted::class);
});

it('uses generic flow when no action key is provided', function (): void {
    $approver = $this->createUser();
    $genericFlow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $actionFlow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()], 'cancel');
    $order = PurchaseOrder::factory()->create();

    $approval = $this->engine->submit($order, submittedBy: $approver->getKey());

    expect($approval->flow->is($genericFlow))->toBeTrue()
        ->and($approval->flow->is($actionFlow))->toBeFalse();
});

it('uses action-specific flow when action key is provided', function (): void {
    $approver = $this->createUser();
    $genericFlow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $actionFlow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()], 'cancel');
    $order = PurchaseOrder::factory()->create();

    $approval = $this->engine->submit($order, submittedBy: $approver->getKey(), actionKey: 'cancel');

    expect($approval->flow->is($actionFlow))->toBeTrue()
        ->and($approval->flow->is($genericFlow))->toBeFalse();
});

it('throws when only action-specific flows exist and no action key is provided', function (): void {
    $approver = $this->createUser();
    $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()], 'cancel');
    $order = PurchaseOrder::factory()->create();

    $this->engine->submit($order, submittedBy: $approver->getKey());
})->throws(ModelNotFoundException::class);

// ─── Approve ──────────────────────────────────────────────────

it('approves single step and completes approval', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create();

    $approval = $this->engine->submit($order, $flow, $approver->getKey());
    $stepInstance = $approval->currentStepInstance();
    expect($stepInstance)->not->toBeNull();

    $this->engine->approve($stepInstance, $approver->getKey(), 'Looks good');

    $approval->refresh();
    expect($approval)
        ->status->toBe(ApprovalStatus::Approved)
        ->completed_at->not->toBeNull();
});

it('triggers model callback on approve', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create(['status' => 'draft']);

    $approval = $this->engine->submit($order, $flow, $approver->getKey());
    $stepInstance = $approval->currentStepInstance();
    expect($stepInstance)->not->toBeNull();

    $this->engine->approve($stepInstance, $approver->getKey());

    $order->refresh();
    expect($order->status)->toBe('approved');
});

it('advances to next step in multi-step approval', function (): void {
    $manager = $this->createUser();
    $director = $this->createUser();

    $flow = $this->createMultiStepFlow(PurchaseOrder::class, [
        ['name' => 'Manager', 'approver_ids' => [$manager->getKey()]],
        ['name' => 'Director', 'approver_ids' => [$director->getKey()]],
    ]);

    $order = PurchaseOrder::factory()->create();
    $approval = $this->engine->submit($order, $flow, $manager->getKey());

    $step1 = $approval->currentStepInstance();
    expect($step1)->not->toBeNull();
    $this->engine->approve($step1, $manager->getKey());

    $approval->refresh();
    expect($approval->status)->toBe(ApprovalStatus::Pending);

    $step2 = $approval->currentStepInstance();
    expect($step2)
        ->not->toBeNull()
        ->status->toBe(StepInstanceStatus::Waiting)
        ->and($step2->assigned_approver_ids)->toContain($director->getKey());
});

it('completes approval when last step is approved', function (): void {
    $manager = $this->createUser();
    $director = $this->createUser();

    $flow = $this->createMultiStepFlow(PurchaseOrder::class, [
        ['name' => 'Manager', 'approver_ids' => [$manager->getKey()]],
        ['name' => 'Director', 'approver_ids' => [$director->getKey()]],
    ]);

    $order = PurchaseOrder::factory()->create(['status' => 'draft']);
    $approval = $this->engine->submit($order, $flow, $manager->getKey());

    $step1 = $approval->currentStepInstance();
    expect($step1)->not->toBeNull();
    $this->engine->approve($step1, $manager->getKey());

    $approval->refresh();
    $step2 = $approval->currentStepInstance();
    expect($step2)->not->toBeNull();
    $this->engine->approve($step2, $director->getKey());

    $approval->refresh();
    expect($approval->status)->toBe(ApprovalStatus::Approved);

    $order->refresh();
    expect($order->status)->toBe('approved');
});

// ─── Reject ───────────────────────────────────────────────────

it('marks approval as rejected', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create(['status' => 'draft']);

    $approval = $this->engine->submit($order, $flow, $approver->getKey());
    $stepInstance = $approval->currentStepInstance();
    expect($stepInstance)->not->toBeNull();

    $this->engine->reject($stepInstance, $approver->getKey(), 'Budget exceeded');

    $approval->refresh();
    expect($approval)
        ->status->toBe(ApprovalStatus::Rejected)
        ->completed_at->not->toBeNull();

    $order->refresh();
    expect($order->status)->toBe('rejected');
});

it('skips remaining steps on rejection', function (): void {
    $manager = $this->createUser();
    $director = $this->createUser();

    $flow = $this->createMultiStepFlow(PurchaseOrder::class, [
        ['name' => 'Manager', 'approver_ids' => [$manager->getKey()]],
        ['name' => 'Director', 'approver_ids' => [$director->getKey()]],
    ]);

    $order = PurchaseOrder::factory()->create();
    $approval = $this->engine->submit($order, $flow, $manager->getKey());

    $step1 = $approval->currentStepInstance();
    expect($step1)->not->toBeNull();
    $this->engine->reject($step1, $manager->getKey(), 'Invalid');

    $approval->refresh();
    $pendingSteps = $approval->stepInstances()
        ->where('status', StepInstanceStatus::Pending)
        ->count();

    $skippedSteps = $approval->stepInstances()
        ->where('status', StepInstanceStatus::Skipped)
        ->count();

    expect($pendingSteps)->toBe(0)
        ->and($skippedSteps)->toBe(1);
});

it('fires approval rejected event', function (): void {
    Event::fake();

    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create();

    $approval = $this->engine->submit($order, $flow, $approver->getKey());
    $stepInstance = $approval->currentStepInstance();
    expect($stepInstance)->not->toBeNull();

    $this->engine->reject($stepInstance, $approver->getKey(), 'No');

    Event::assertDispatched(ApprovalRejected::class);
});

// ─── Comment ──────────────────────────────────────────────────

it('records comment action', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create();

    $approval = $this->engine->submit($order, $flow, $approver->getKey());

    $this->engine->comment($approval, $approver->getKey(), 'Need more details');

    $commentAction = $approval->actions()
        ->where('type', ActionType::Commented)
        ->first();

    expect($commentAction)
        ->not->toBeNull()
        ->comment->toBe('Need more details');
});

// ─── Delegate ─────────────────────────────────────────────────

it('creates delegation record', function (): void {
    $approver = $this->createUser();
    $delegate = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create();

    $approval = $this->engine->submit($order, $flow, $approver->getKey());
    $stepInstance = $approval->currentStepInstance();
    expect($stepInstance)->not->toBeNull();

    $this->engine->delegate($stepInstance, $approver->getKey(), $delegate->getKey(), 'On vacation');

    $delegation = $stepInstance->delegations()->first();
    expect($delegation)
        ->not->toBeNull()
        ->from_user_id->toBe($approver->getKey())
        ->to_user_id->toBe($delegate->getKey())
        ->reason->toBe('On vacation');
});

it('allows delegated user to act on step', function (): void {
    $approver = $this->createUser();
    $delegate = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create();

    $approval = $this->engine->submit($order, $flow, $approver->getKey());
    $stepInstance = $approval->currentStepInstance();
    expect($stepInstance)->not->toBeNull();

    $this->engine->delegate($stepInstance, $approver->getKey(), $delegate->getKey());

    expect($stepInstance->canUserAct($delegate->getKey()))->toBeTrue();
});

it('allows delegate to approve step', function (): void {
    $approver = $this->createUser();
    $delegate = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create(['status' => 'draft']);

    $approval = $this->engine->submit($order, $flow, $approver->getKey());
    $stepInstance = $approval->currentStepInstance();
    expect($stepInstance)->not->toBeNull();

    $this->engine->delegate($stepInstance, $approver->getKey(), $delegate->getKey());
    $this->engine->approve($stepInstance, $delegate->getKey());

    $approval->refresh();
    expect($approval->status)->toBe(ApprovalStatus::Approved);

    $order->refresh();
    expect($order->status)->toBe('approved');
});

// ─── Cancel ───────────────────────────────────────────────────

it('marks approval as cancelled', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create();

    $approval = $this->engine->submit($order, $flow, $approver->getKey());

    $this->engine->cancel($approval);

    $approval->refresh();
    expect($approval->status)->toBe(ApprovalStatus::Cancelled);
});

it('skips all pending steps on cancel', function (): void {
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

    expect($activeSteps)->toBe(0);
});

// ─── HasApprovals trait ───────────────────────────────────────

it('provides approvals relationship on model', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create();

    $order->submitForApproval($flow, $approver->getKey());

    expect($order->approvals)->toHaveCount(1)
        ->and($order->isPendingApproval())->toBeTrue()
        ->and($order->isApproved())->toBeFalse();
});

it('reports correct approval status on model', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create();

    $approval = $order->submitForApproval($flow, $approver->getKey());

    expect($order->approvalStatus())->toBe(ApprovalStatus::Pending)
        ->and($order->isPendingApproval())->toBeTrue()
        ->and($order->isApproved())->toBeFalse()
        ->and($order->isRejected())->toBeFalse();

    $stepInstance = $approval->currentStepInstance();
    expect($stepInstance)->not->toBeNull();
    $this->engine->approve($stepInstance, $approver->getKey());

    $order->refresh();
    expect($order->approvalStatus())->toBe(ApprovalStatus::Approved)
        ->and($order->isApproved())->toBeTrue()
        ->and($order->isPendingApproval())->toBeFalse();
});

it('prevents submission while pending', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create();

    $order->submitForApproval($flow, $approver->getKey());

    expect($order->canBeSubmittedForApproval())->toBeFalse();
});
