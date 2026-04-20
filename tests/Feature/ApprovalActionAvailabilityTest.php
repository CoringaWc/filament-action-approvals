<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\ApproverResolvers\UserResolver;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;

it('exposes action availability for the directly assigned approver', function (): void {
    $engine = app(ApprovalEngine::class);
    $approver = User::factory()->create();
    $flow = ApprovalFlow::create([
        'name' => 'Direct Approval Flow',
        'approvable_type' => (new PurchaseOrder)->getMorphClass(),
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
    $approval = $engine->submit($order, $flow, $approver->getKey());
    $stepInstance = $approval->currentStepInstance();

    expect($stepInstance)->toBeInstanceOf(ApprovalStepInstance::class)
        ->and($approval->canBeApprovedBy($approver->getKey()))->toBeTrue()
        ->and($approval->canBeRejectedBy($approver->getKey()))->toBeTrue()
        ->and($approval->canReceiveCommentsFrom($approver->getKey()))->toBeTrue()
        ->and($approval->canBeDelegatedBy($approver->getKey()))->toBeTrue();

    if (! $stepInstance instanceof ApprovalStepInstance) {
        throw new RuntimeException('Expected current step instance to exist.');
    }

    expect($stepInstance->canBeApprovedBy($approver->getKey()))->toBeTrue()
        ->and($stepInstance->canBeRejectedBy($approver->getKey()))->toBeTrue()
        ->and($stepInstance->canReceiveCommentsFrom($approver->getKey()))->toBeTrue()
        ->and($stepInstance->canBeDelegatedBy($approver->getKey()))->toBeTrue();
});

it('exposes delegated availability without allowing re-delegation', function (): void {
    $engine = app(ApprovalEngine::class);
    $approver = User::factory()->create();
    $delegate = User::factory()->create();
    $flow = ApprovalFlow::create([
        'name' => 'Delegation Availability Flow',
        'approvable_type' => (new PurchaseOrder)->getMorphClass(),
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
    $approval = $engine->submit($order, $flow, $approver->getKey());
    $stepInstance = $approval->currentStepInstance();

    expect($stepInstance)->toBeInstanceOf(ApprovalStepInstance::class);

    if (! $stepInstance instanceof ApprovalStepInstance) {
        throw new RuntimeException('Expected current step instance to exist.');
    }

    $engine->delegate($stepInstance, $approver->getKey(), $delegate->getKey(), 'Covering this approval');

    $approval->refresh();
    $stepInstance->refresh();

    expect($approval->canBeApprovedBy($delegate->getKey()))->toBeTrue()
        ->and($approval->canBeRejectedBy($delegate->getKey()))->toBeTrue()
        ->and($approval->canReceiveCommentsFrom($delegate->getKey()))->toBeTrue()
        ->and($approval->canBeDelegatedBy($delegate->getKey()))->toBeFalse();

    expect($stepInstance->canBeApprovedBy($delegate->getKey()))->toBeTrue()
        ->and($stepInstance->canBeRejectedBy($delegate->getKey()))->toBeTrue()
        ->and($stepInstance->canReceiveCommentsFrom($delegate->getKey()))->toBeTrue()
        ->and($stepInstance->canBeDelegatedBy($delegate->getKey()))->toBeFalse();
});

it('returns false for users without operational access and for completed approvals', function (): void {
    $engine = app(ApprovalEngine::class);
    $approver = User::factory()->create();
    $outsider = User::factory()->create();
    $flow = ApprovalFlow::create([
        'name' => 'Negative Availability Flow',
        'approvable_type' => (new PurchaseOrder)->getMorphClass(),
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
    $approval = $engine->submit($order, $flow, $approver->getKey());
    $stepInstance = $approval->currentStepInstance();

    expect($stepInstance)->toBeInstanceOf(ApprovalStepInstance::class)
        ->and($approval->canBeApprovedBy($outsider->getKey()))->toBeFalse()
        ->and($approval->canBeRejectedBy($outsider->getKey()))->toBeFalse()
        ->and($approval->canReceiveCommentsFrom($outsider->getKey()))->toBeFalse()
        ->and($approval->canBeDelegatedBy($outsider->getKey()))->toBeFalse();

    if (! $stepInstance instanceof ApprovalStepInstance) {
        throw new RuntimeException('Expected current step instance to exist.');
    }

    $engine->approve($stepInstance, $approver->getKey());

    $approval->refresh();

    expect($approval->canBeApprovedBy($approver->getKey()))->toBeFalse()
        ->and($approval->canBeRejectedBy($approver->getKey()))->toBeFalse()
        ->and($approval->canReceiveCommentsFrom($approver->getKey()))->toBeFalse()
        ->and($approval->canBeDelegatedBy($approver->getKey()))->toBeFalse();
});

it('allows super admins through operational availability helpers', function (): void {
    config()->set('filament-action-approvals.super_admin.enabled', true);

    $engine = app(ApprovalEngine::class);
    $approver = User::factory()->create();
    $superAdmin = User::factory()->create();

    config()->set('filament-action-approvals.super_admin.user_ids', [$superAdmin->getKey()]);

    $flow = ApprovalFlow::create([
        'name' => 'Super Admin Availability Flow',
        'approvable_type' => (new PurchaseOrder)->getMorphClass(),
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
    $approval = $engine->submit($order, $flow, $approver->getKey());
    $stepInstance = $approval->currentStepInstance();

    expect($stepInstance)->toBeInstanceOf(ApprovalStepInstance::class)
        ->and($approval->canBeApprovedBy($superAdmin->getKey()))->toBeTrue()
        ->and($approval->canBeRejectedBy($superAdmin->getKey()))->toBeTrue()
        ->and($approval->canReceiveCommentsFrom($superAdmin->getKey()))->toBeTrue()
        ->and($approval->canBeDelegatedBy($superAdmin->getKey()))->toBeTrue();
});
