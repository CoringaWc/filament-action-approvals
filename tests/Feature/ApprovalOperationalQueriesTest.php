<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\ApproverResolvers\UserResolver;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;

it('filters approvals by approvable record and status', function (): void {
    $engine = app(ApprovalEngine::class);
    $approver = User::factory()->create();
    $flow = ApprovalFlow::create([
        'name' => 'Operational Query Flow',
        'approvable_type' => (new PurchaseOrder)->getMorphClass(),
        'action_key' => 'submit',
        'is_active' => true,
    ]);
    $flow->steps()->create([
        'name' => 'Step 1',
        'order' => 1,
        'type' => StepType::Single,
        'approver_resolver' => UserResolver::class,
        'approver_config' => ['user_ids' => [$approver->getKey()]],
        'required_approvals' => 1,
    ]);

    $orderWithApprovedApproval = PurchaseOrder::factory()->create();
    $otherOrder = PurchaseOrder::factory()->create();

    $approvedApproval = $engine->submit($orderWithApprovedApproval, $flow, $approver->getKey(), 'submit');
    $approvedStep = $approvedApproval->currentStepInstance();
    expect($approvedStep)->not->toBeNull();

    if (! $approvedStep instanceof ApprovalStepInstance) {
        throw new RuntimeException('Expected current step instance to exist.');
    }

    $engine->approve($approvedStep, $approver->getKey());

    $pendingApproval = $engine->submit($otherOrder, $flow, $approver->getKey(), 'submit');

    $matchingApprovals = Approval::query()
        ->forApprovable($otherOrder)
        ->withStatus(ApprovalStatus::Pending)
        ->get();

    expect($matchingApprovals)
        ->toHaveCount(1)
        ->and($matchingApprovals->first()?->is($pendingApproval))->toBeTrue()
        ->and(Approval::query()->forApprovable($orderWithApprovedApproval)->withStatus(ApprovalStatus::Approved)->count())->toBe(1);
});

it('returns approvals and step instances awaiting delegated user action', function (): void {
    $engine = app(ApprovalEngine::class);
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $delegate = User::factory()->create();
    $otherUser = User::factory()->create();

    $flow = ApprovalFlow::create([
        'name' => 'Delegated Approval Flow',
        'approvable_type' => (new PurchaseOrder)->getMorphClass(),
        'action_key' => 'submit',
        'is_active' => true,
    ]);
    $flow->steps()->create([
        'name' => 'Step 1',
        'order' => 1,
        'type' => StepType::Single,
        'approver_resolver' => UserResolver::class,
        'approver_config' => ['user_ids' => [$approver->getKey()]],
        'required_approvals' => 1,
    ]);

    $order = PurchaseOrder::factory()->create();

    $approval = $engine->submit($order, $flow, $requester->getKey(), 'submit');
    $stepInstance = $approval->currentStepInstance();
    expect($stepInstance)->not->toBeNull();

    if (! $stepInstance instanceof ApprovalStepInstance) {
        throw new RuntimeException('Expected current step instance to exist.');
    }

    $engine->delegate($stepInstance, $approver->getKey(), $delegate->getKey(), 'Covering this approval');

    $approval->loadMissing('stepInstances.delegations');
    $stepInstance->loadMissing('delegations');

    expect(Approval::query()->awaitingUserAction($delegate->getKey())->get())
        ->toHaveCount(1)
        ->and(Approval::query()->awaitingUserAction($delegate->getKey())->first()?->is($approval))->toBeTrue()
        ->and($approval->isAwaitingUserAction($delegate->getKey()))->toBeTrue()
        ->and($approval->isAwaitingUserAction($otherUser->getKey()))->toBeFalse();

    expect(ApprovalStepInstance::query()->waiting()->assignedTo($delegate->getKey())->get())
        ->toHaveCount(1)
        ->and(ApprovalStepInstance::query()->waiting()->assignedTo($delegate->getKey())->first()?->is($stepInstance))->toBeTrue()
        ->and($stepInstance->isAssignedTo($delegate->getKey()))->toBeTrue()
        ->and($stepInstance->canUserAct($delegate->getKey()))->toBeTrue()
        ->and(ApprovalStepInstance::query()->waiting()->assignedTo($otherUser->getKey())->count())->toBe(0);
});
