<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\ApproverResolvers\UserResolver;
use CoringaWc\FilamentActionApprovals\Attributes\ApprovableOperation;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalOperation;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Resources\Approvals\Pages\ListApprovals;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Support\ApprovalActionRegistry;
use CoringaWc\FilamentActionApprovals\Support\ApprovalOperationSubmissionContext;
use CoringaWc\FilamentActionApprovals\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DatabaseSeeder;

beforeEach(function (): void {
    app(ApprovalActionRegistry::class)->flush();
});

it('applies registered action handlers when an approval is completed', function (): void {
    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    $order = PurchaseOrder::factory()->for($submitter, 'user')->create(['title' => 'Original order']);
    $flow = createRegistrySingleStepFlow(PurchaseOrder::class, $approver, 'purchase-order.archive');
    $calls = 0;

    app(ApprovalActionRegistry::class)->applyUsing(
        PurchaseOrder::class,
        'purchase-order.archive',
        ApprovalActionRegistry::OperationAction,
        function (Model $record, Approval $approval, array $payload, int|string|null $approvedBy) use (&$calls, $approver): void {
            $calls++;

            expect($approvedBy)->toBe($approver->getKey())
                ->and($approval->submittedActionKey())->toBe('purchase-order.archive');

            $record->update(['title' => $payload['title']]);
        },
    );

    $approval = app(ApprovalEngine::class)->submit(
        $order,
        $flow,
        $submitter,
        'purchase-order.archive',
        ['payload' => ['title' => 'Archived order']],
    );

    $stepInstance = $approval->currentStepInstance();

    if (! $stepInstance instanceof ApprovalStepInstance) {
        throw new RuntimeException('Expected approval to have a current waiting step instance.');
    }

    app(ApprovalEngine::class)->approve($stepInstance, $approver->getKey());

    expect($order->refresh()->getAttribute('title'))->toBe('Archived order');
    expect($approval->refresh()->status)->toBe(ApprovalStatus::Approved);
    expect(data_get($approval->metadata, 'applied_at'))->not->toBeNull();
    expect(data_get($approval->metadata, 'applied_via'))->toBe('handler');
    expect($calls)->toBe(1);

    app(ApprovalEngine::class)->activateNextStep($approval->refresh());

    expect($calls)->toBe(1);
    expect($order->refresh()->getAttribute('title'))->toBe('Archived order');
});

it('records registered handler failures without rolling back approval completion', function (): void {
    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    $order = PurchaseOrder::factory()->for($submitter, 'user')->create(['title' => 'Original order']);
    $flow = createRegistrySingleStepFlow(PurchaseOrder::class, $approver, 'purchase-order.fail');

    app(ApprovalActionRegistry::class)->applyUsing(
        PurchaseOrder::class,
        'purchase-order.fail',
        ApprovalActionRegistry::OperationAction,
        fn (): never => throw ValidationException::withMessages(['approval' => 'Cannot apply.']),
    );

    $approval = app(ApprovalEngine::class)->submit(
        $order,
        $flow,
        $submitter,
        'purchase-order.fail',
        ['payload' => ['title' => 'Should not apply']],
    );

    $stepInstance = $approval->currentStepInstance();

    if (! $stepInstance instanceof ApprovalStepInstance) {
        throw new RuntimeException('Expected approval to have a current waiting step instance.');
    }

    app(ApprovalEngine::class)->approve($stepInstance, $approver->getKey());

    expect($approval->refresh()->status)->toBe(ApprovalStatus::Approved)
        ->and($order->refresh()->getAttribute('title'))->toBe('Original order')
        ->and(data_get($approval->metadata, 'applied_at'))->toBeNull()
        ->and(data_get($approval->metadata, 'apply_failed_at'))->not->toBeNull()
        ->and(data_get($approval->metadata, 'apply_failed_reason'))->toBe(__('filament-action-approvals::approval.actions.approved_apply_failed'))
        ->and(data_get($approval->metadata, 'apply_failed_exception'))->toBe('ValidationException');
});

it('runs registered submit handlers inside approval submission', function (): void {
    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    $order = PurchaseOrder::factory()->for($submitter, 'user')->create(['title' => 'Original order']);
    $flow = createRegistrySingleStepFlow(PurchaseOrder::class, $approver, 'purchase-order.submit-hook');
    $calls = 0;

    app(ApprovalActionRegistry::class)->submitUsing(
        PurchaseOrder::class,
        'purchase-order.submit-hook',
        ApprovalOperation::Update,
        function (ApprovalOperationSubmissionContext $context) use (&$calls, $order): void {
            $calls++;

            expect($context->approvable->is($order))->toBeTrue()
                ->and($context->actionKey)->toBe('purchase-order.submit-hook')
                ->and($context->operation)->toBe(ApprovalOperation::Update->value)
                ->and($context->governedPayload)->toBe(['title' => 'Submitted title']);
        },
    );

    $approval = app(ApprovalEngine::class)->submit(
        $order,
        $flow,
        $submitter,
        'purchase-order.submit-hook',
        [
            'operation' => ['name' => ApprovalOperation::Update->value],
            'payload' => ['title' => 'Submitted title'],
        ],
        afterApprovalCreated: function (Approval $approval) use ($order): void {
            $handler = app(ApprovalActionRegistry::class)->resolveSubmitHandler(
                $approval,
                $order,
                'purchase-order.submit-hook',
                ApprovalOperation::Update->value,
            );

            expect($handler)->not->toBeNull();

            $handler(new ApprovalOperationSubmissionContext(
                approval: $approval,
                approvable: $order,
                definition: new ApprovableOperation(actionKey: 'purchase-order.submit-hook'),
                actionKey: 'purchase-order.submit-hook',
                operation: ApprovalOperation::Update->value,
                governedPayload: ['title' => 'Submitted title'],
                directPayload: [],
                ignoredPayloadKeys: [],
                submittedBy: null,
            ));
        },
    );

    expect($approval->exists)->toBeTrue()
        ->and($calls)->toBe(1);
});

it('flushes submit handlers with apply handlers', function (): void {
    $submitter = User::factory()->create();
    $order = PurchaseOrder::factory()->for($submitter, 'user')->create();
    $approval = new Approval([
        'approvable_type' => $order->getMorphClass(),
        'approvable_id' => $order->getKey(),
        'action_key' => 'purchase-order.submit-hook',
    ]);

    app(ApprovalActionRegistry::class)
        ->applyUsing(PurchaseOrder::class, 'purchase-order.submit-hook', ApprovalOperation::Update->value, fn (): null => null)
        ->submitUsing(PurchaseOrder::class, 'purchase-order.submit-hook', ApprovalOperation::Update, fn (): null => null);

    expect(app(ApprovalActionRegistry::class)->resolveApplyHandler($approval, $order, 'purchase-order.submit-hook', ApprovalOperation::Update->value))->not->toBeNull()
        ->and(app(ApprovalActionRegistry::class)->resolveSubmitHandler($approval, $order, 'purchase-order.submit-hook', ApprovalOperation::Update->value))->not->toBeNull();

    app(ApprovalActionRegistry::class)->flush();

    expect(app(ApprovalActionRegistry::class)->resolveApplyHandler($approval, $order, 'purchase-order.submit-hook', ApprovalOperation::Update->value))->toBeNull()
        ->and(app(ApprovalActionRegistry::class)->resolveSubmitHandler($approval, $order, 'purchase-order.submit-hook', ApprovalOperation::Update->value))->toBeNull();
});

it('uses registered handlers before the crud fallback', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $submitter = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();
    $approver = User::query()->where('email', 'manager@filament-action-approvals.test')->firstOrFail();

    $test->actingAs($submitter);

    createRegistrySingleStepFlow(PurchaseOrder::class, $approver, 'purchase-order.edit');

    $order = PurchaseOrder::factory()->for($submitter, 'user')->create([
        'title' => 'Original order',
        'amount' => 1200,
    ]);

    app(ApprovalActionRegistry::class)->applyUsing(
        PurchaseOrder::class,
        'purchase-order.edit',
        ApprovalOperation::Update->value,
        function (Model $record): void {
            $record->update(['title' => 'Applied by handler']);
        },
    );

    Livewire::test(ListPurchaseOrders::class)
        ->callTableAction('edit', $order, [
            'user_id' => $submitter->getKey(),
            'title' => 'Updated by CRUD fallback',
            'description' => $order->getAttribute('description'),
            'amount' => 1500,
        ])
        ->assertNotified(__('filament-action-approvals::approval.actions.approval_request_submitted'));

    $approval = $order->approvals()->firstOrFail();

    $stepInstance = $approval->currentStepInstance();

    if (! $stepInstance instanceof ApprovalStepInstance) {
        throw new RuntimeException('Expected approval to have a current waiting step instance.');
    }

    app(ApprovalEngine::class)->approve($stepInstance, $approver->getKey());

    expect($order->refresh()->getAttribute('title'))->toBe('Applied by handler');
    expect($order->getAttribute('amount'))->toEqual('1200.00');
    expect(data_get($approval->refresh()->metadata, 'operation.applied_at'))->toBeNull();
    expect(data_get($approval->metadata, 'applied_via'))->toBe('handler');
});

it('notifies operational failure from approval record actions', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    $order = PurchaseOrder::factory()->for($submitter, 'user')->create(['title' => 'Original order']);
    $flow = createRegistrySingleStepFlow(PurchaseOrder::class, $approver, 'purchase-order.fail-action');

    app(ApprovalActionRegistry::class)->applyUsing(
        PurchaseOrder::class,
        'purchase-order.fail-action',
        ApprovalActionRegistry::OperationAction,
        fn (): never => throw ValidationException::withMessages(['approval' => 'Cannot apply.']),
    );

    $approval = app(ApprovalEngine::class)->submit(
        $order,
        $flow,
        $submitter,
        'purchase-order.fail-action',
        ['payload' => ['title' => 'Should not apply']],
    );

    $test->actingAs($approver);

    Livewire::test(ListApprovals::class)
        ->callTableAction('approve', $approval, [
            'comment' => 'Approved for operational failure test.',
        ])
        ->assertNotified(__('filament-action-approvals::approval.actions.approved_apply_failed'))
        ->assertNotNotified(__('filament-action-approvals::approval.actions.approved_success'));

    expect($approval->refresh()->status)->toBe(ApprovalStatus::Approved)
        ->and(data_get($approval->metadata, 'apply_failed_at'))->not->toBeNull();
});

/**
 * @param  class-string<Model>  $modelClass
 */
function createRegistrySingleStepFlow(string $modelClass, User $approver, string $actionKey): ApprovalFlow
{
    $flow = ApprovalFlow::create([
        'name' => 'Test Flow',
        'approvable_type' => (new $modelClass)->getMorphClass(),
        'action_key' => $actionKey,
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

    return $flow;
}
