<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\ApproverResolvers\UserResolver;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlows\ApprovalFlowResource;
use CoringaWc\FilamentActionApprovals\Resources\Approvals\ApprovalResource;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Support\ApprovalModels;
use CoringaWc\FilamentActionApprovals\Tests\Fixtures\Models\CustomApproval;
use CoringaWc\FilamentActionApprovals\Tests\Fixtures\Models\CustomApprovalAction;
use CoringaWc\FilamentActionApprovals\Tests\Fixtures\Models\CustomApprovalDelegation;
use CoringaWc\FilamentActionApprovals\Tests\Fixtures\Models\CustomApprovalFlow;
use CoringaWc\FilamentActionApprovals\Tests\Fixtures\Models\CustomApprovalStep;
use CoringaWc\FilamentActionApprovals\Tests\Fixtures\Models\CustomApprovalStepInstance;
use Workbench\App\Models\PurchaseOrder;

beforeEach(function (): void {
    config()->set('filament-action-approvals.models', [
        'approval' => CustomApproval::class,
        'approval_flow' => CustomApprovalFlow::class,
        'approval_step' => CustomApprovalStep::class,
        'approval_step_instance' => CustomApprovalStepInstance::class,
        'approval_action' => CustomApprovalAction::class,
        'approval_delegation' => CustomApprovalDelegation::class,
    ]);
});

it('resolves extended package models from configuration', function (): void {
    expect(ApprovalModels::approval())->toBe(CustomApproval::class)
        ->and(ApprovalModels::flow())->toBe(CustomApprovalFlow::class)
        ->and(ApprovalModels::step())->toBe(CustomApprovalStep::class)
        ->and(ApprovalModels::stepInstance())->toBe(CustomApprovalStepInstance::class)
        ->and(ApprovalModels::action())->toBe(CustomApprovalAction::class)
        ->and(ApprovalModels::delegation())->toBe(CustomApprovalDelegation::class)
        ->and(ApprovalResource::getModel())->toBe(CustomApproval::class)
        ->and(ApprovalFlowResource::getModel())->toBe(CustomApprovalFlow::class)
        ->and((new CustomApproval)->getTable())->toBe('approvals')
        ->and((new CustomApprovalFlow)->getTable())->toBe('approval_flows')
        ->and((new CustomApprovalStep)->getTable())->toBe('approval_steps')
        ->and((new CustomApprovalStepInstance)->getTable())->toBe('approval_step_instances')
        ->and((new CustomApprovalAction)->getTable())->toBe('approval_actions')
        ->and((new CustomApprovalDelegation)->getTable())->toBe('approval_delegations');
});

it('uses extended models across approval creation and relationships', function (): void {
    $approver = $this->createUser();
    $delegate = $this->createUser();
    $order = PurchaseOrder::factory()->create();
    $flowModel = ApprovalModels::flow();

    $flow = $flowModel::create([
        'name' => 'Custom Model Flow',
        'approvable_type' => $order->getMorphClass(),
        'is_active' => true,
    ]);

    $step = $flow->steps()->create([
        'name' => 'Custom Step',
        'order' => 1,
        'type' => StepType::Single,
        'approver_resolver' => UserResolver::class,
        'approver_config' => ['user_ids' => [$approver->getKey()]],
        'required_approvals' => 1,
    ]);

    $approval = app(ApprovalEngine::class)->submit($order, $flow, $approver->getKey());
    $approval->load(['flow', 'stepInstances.step', 'actions']);
    $stepInstance = $approval->currentStepInstance();

    expect($flow)->toBeInstanceOf(CustomApprovalFlow::class)
        ->and($step)->toBeInstanceOf(CustomApprovalStep::class)
        ->and($approval)->toBeInstanceOf(CustomApproval::class)
        ->and($approval->flow)->toBeInstanceOf(CustomApprovalFlow::class)
        ->and($approval->stepInstances->first())->toBeInstanceOf(CustomApprovalStepInstance::class)
        ->and($stepInstance)->toBeInstanceOf(CustomApprovalStepInstance::class)
        ->and($stepInstance?->step)->toBeInstanceOf(CustomApprovalStep::class)
        ->and($approval->actions->first())->toBeInstanceOf(CustomApprovalAction::class);

    app(ApprovalEngine::class)->delegate($stepInstance, $approver->getKey(), $delegate->getKey(), 'Delegate');

    $stepInstance->refresh()->load('delegations');

    expect($stepInstance->delegations->first())->toBeInstanceOf(CustomApprovalDelegation::class);
});

it('keeps default models when configuration only overrides one key', function (): void {
    config()->set('filament-action-approvals.models', [
        'approval' => CustomApproval::class,
    ]);

    expect(ApprovalModels::approval())->toBe(CustomApproval::class)
        ->and(ApprovalModels::flow())->toBe(ApprovalFlow::class);
});

it('rejects model overrides that do not extend the package model', function (): void {
    config()->set('filament-action-approvals.models.approval', PurchaseOrder::class);

    expect(fn (): string => ApprovalModels::approval())->toThrow(InvalidArgumentException::class);
});
