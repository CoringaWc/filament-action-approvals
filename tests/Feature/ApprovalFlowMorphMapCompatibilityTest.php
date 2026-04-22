<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\ApproverResolvers\UserResolver;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Illuminate\Database\Eloquent\Relations\Relation;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\User;
use Workbench\App\States\Invoice\Issuing;
use Workbench\App\States\Invoice\Sent;

it('normalizes approvable_type to the morph alias when saving a flow', function (): void {
    Relation::morphMap([
        'invoice_alias' => Invoice::class,
    ]);

    $flow = ApprovalFlow::create([
        'name' => 'Morph Alias Flow',
        'approvable_type' => Invoice::class,
        'action_key' => Invoice::stateApprovalActionKey(Issuing::class, Sent::class),
        'is_active' => true,
    ]);

    expect($flow->approvable_type)->toBe('invoice_alias');
});

it('resolves legacy fqcn flows even when the model now uses a morph alias', function (): void {
    Relation::morphMap([
        'invoice_alias' => Invoice::class,
    ]);

    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $invoice = Invoice::factory()->for($requester)->issuing()->create();
    $actionKey = Invoice::stateApprovalActionKey(Issuing::class, Sent::class);

    $flow = ApprovalFlow::create([
        'name' => 'Legacy FQCN Flow',
        'approvable_type' => null,
        'action_key' => $actionKey,
        'is_active' => true,
    ]);

    $flow->forceFill([
        'approvable_type' => Invoice::class,
    ])->saveQuietly();

    $flow->steps()->create([
        'name' => 'Step 1',
        'order' => 1,
        'type' => 'single',
        'approver_resolver' => UserResolver::class,
        'approver_config' => ['user_ids' => [$approver->getKey()]],
        'required_approvals' => 1,
    ]);

    $result = $invoice->transitionWithApproval('status', Sent::class, submittedBy: $requester->getKey());

    $approval = $result->approval;

    expect($result->pendingApproval)->toBeTrue()
        ->and($approval)->not->toBeNull();

    if ($approval === null) {
        throw new RuntimeException('Expected an approval instance to be created.');
    }

    $freshFlow = $flow->fresh();

    if ($freshFlow === null) {
        throw new RuntimeException('Expected the approval flow to be persisted.');
    }

    expect($approval->approval_flow_id)->toBe($freshFlow->getKey());

    $invoice->refresh();

    expect($invoice->isIssuing())->toBeTrue();

    $stepInstance = $approval->currentStepInstance();

    expect($stepInstance)->not->toBeNull();

    if ($stepInstance === null) {
        throw new RuntimeException('Expected a current step instance for the pending approval.');
    }

    app(ApprovalEngine::class)->approve($stepInstance, $approver->getKey());

    $invoice->refresh();

    expect($invoice->isSent())->toBeTrue();
});
