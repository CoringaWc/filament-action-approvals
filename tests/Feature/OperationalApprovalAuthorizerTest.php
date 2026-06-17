<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\ApproverResolvers\UserResolver;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Support\OperationalApprovalAuthorizer;
use Illuminate\Auth\Access\AuthorizationException;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;

it('authorizes only eligible operational approvers', function (): void {
    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    $outsider = User::factory()->create();
    $flow = approvalAuthorizerSingleStepFlow([$approver->getKey()]);
    $order = PurchaseOrder::factory()->for($submitter, 'user')->create();

    $approval = app(ApprovalEngine::class)->submit($order, $flow, $submitter->getKey());

    expect($approval->currentStepInstance())->toBeInstanceOf(ApprovalStepInstance::class);

    $authorizer = app(OperationalApprovalAuthorizer::class);

    expect($authorizer->canApprove($approval, $approver->getKey()))->toBeTrue()
        ->and($authorizer->canReject($approval, $approver->getKey()))->toBeTrue()
        ->and($authorizer->canApprove($approval, $outsider->getKey()))->toBeFalse()
        ->and($authorizer->canReject($approval, $outsider->getKey()))->toBeFalse();

    expect(fn (): null => $authorizer->ensureCanApprove($approval, $outsider->getKey()))
        ->toThrow(AuthorizationException::class);
});

it('applies the plugin operational authorization callback before engine execution', function (): void {
    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    $flow = approvalAuthorizerSingleStepFlow([$approver->getKey()]);
    $order = PurchaseOrder::factory()->for($submitter, 'user')->create();

    $approval = app(ApprovalEngine::class)->submit($order, $flow, $submitter->getKey());
    $plugin = FilamentActionApprovalsPlugin::current();

    $plugin?->authorizeApprovalActionsUsing(fn (): bool => false);

    $authorizer = app(OperationalApprovalAuthorizer::class);

    expect($authorizer->canApprove($approval, $approver->getKey()))->toBeFalse();

    expect(fn (): null => $authorizer->ensureCanApprove($approval, $approver->getKey()))
        ->toThrow(AuthorizationException::class);

    $plugin?->authorizeApprovalActionsUsing(fn (): bool => true);
});

/**
 * @param  array<int, int|string>  $approverIds
 */
function approvalAuthorizerSingleStepFlow(array $approverIds): ApprovalFlow
{
    $flow = ApprovalFlow::create([
        'name' => 'Operational Authorizer Flow',
        'approvable_type' => (new PurchaseOrder)->getMorphClass(),
        'is_active' => true,
    ]);

    $flow->steps()->create([
        'name' => 'Step 1',
        'order' => 1,
        'type' => StepType::Single,
        'approver_resolver' => UserResolver::class,
        'approver_config' => ['user_ids' => $approverIds],
        'required_approvals' => 1,
    ]);

    return $flow;
}
