<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\ApproverResolvers\UserResolver;
use CoringaWc\FilamentActionApprovals\Enums\ActionType;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Resources\Approvals\Pages\ListApprovals;
use CoringaWc\FilamentActionApprovals\Resources\Approvals\Schemas\ApprovalInfolist;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Tests\TestCase;
use Filament\Actions\ViewAction;
use Livewire\Livewire;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DatabaseSeeder;

it('shows approval actions inside the current step section of the approval slide over', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();

    $test->actingAs($admin);

    $flow = ApprovalFlow::create([
        'name' => 'Current Step Actions Flow',
        'approvable_type' => (new PurchaseOrder)->getMorphClass(),
        'is_active' => true,
    ]);
    $flow->steps()->create([
        'name' => 'Step 1',
        'order' => 1,
        'type' => StepType::Single,
        'approver_resolver' => UserResolver::class,
        'approver_config' => ['user_ids' => [$admin->getKey()]],
        'required_approvals' => 1,
    ]);

    $order = PurchaseOrder::factory()->create();
    $approval = app(ApprovalEngine::class)->submit($order, $flow, $admin->getKey());

    Livewire::test(ListApprovals::class)
        ->mountTableAction('view', $approval)
        ->assertInfolistActionVisible('currentStepApprovalActions', 'approve', 'mountedActionSchema0')
        ->assertInfolistActionVisible('currentStepApprovalActions', 'reject', 'mountedActionSchema0');
});

it('shows the submitted approvable action in the approval slide over infolist', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();

    $test->actingAs($admin);

    $flow = ApprovalFlow::create([
        'name' => 'Generic Submission Flow',
        'approvable_type' => (new PurchaseOrder)->getMorphClass(),
        'is_active' => true,
    ]);
    $flow->steps()->create([
        'name' => 'Step 1',
        'order' => 1,
        'type' => StepType::Single,
        'approver_resolver' => UserResolver::class,
        'approver_config' => ['user_ids' => [$admin->getKey()]],
        'required_approvals' => 1,
    ]);

    $order = PurchaseOrder::factory()->create();
    $approval = app(ApprovalEngine::class)->submit($order, $flow, $admin->getKey(), 'submit');

    expect($approval->submittedActionKey())->toBe('submit');

    Livewire::test(ListApprovals::class)
        ->mountTableAction('view', $approval)
        ->assertSeeText(__('workbench::workbench.approval_actions.purchase_orders.submit'), false);
});

it('stores the submitted filament action key in the approval audit trail', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $admin = User::factory()->create();

    $test->actingAs($admin);

    $flow = ApprovalFlow::create([
        'name' => 'Audit Action Flow',
        'approvable_type' => (new PurchaseOrder)->getMorphClass(),
        'is_active' => true,
    ]);
    $flow->steps()->create([
        'name' => 'Step 1',
        'order' => 1,
        'type' => StepType::Single,
        'approver_resolver' => UserResolver::class,
        'approver_config' => ['user_ids' => [$admin->getKey()]],
        'required_approvals' => 1,
    ]);

    $approval = app(ApprovalEngine::class)->submit(
        PurchaseOrder::factory()->create(),
        $flow,
        $admin->getKey(),
        'submit',
    );

    $submittedAction = $approval->actions()->where('type', ActionType::Submitted)->latest()->first();

    expect(data_get($submittedAction, 'metadata.action_key'))->toBe('submit')
        ->and($approval->fresh()->submittedActionKey())->toBe('submit');
});

it('uses the submitted action key in the approval slide over heading', function (): void {
    $admin = User::factory()->create();

    $flow = ApprovalFlow::create([
        'name' => 'Heading Flow',
        'approvable_type' => (new PurchaseOrder)->getMorphClass(),
        'is_active' => true,
    ]);
    $flow->steps()->create([
        'name' => 'Step 1',
        'order' => 1,
        'type' => StepType::Single,
        'approver_resolver' => UserResolver::class,
        'approver_config' => ['user_ids' => [$admin->getKey()]],
        'required_approvals' => 1,
    ]);

    $approval = app(ApprovalEngine::class)->submit(
        PurchaseOrder::factory()->create(),
        $flow,
        $admin->getKey(),
        'submit',
    );

    $viewAction = ApprovalInfolist::configureViewAction(ViewAction::make())
        ->record($approval);

    expect((string) $viewAction->getModalHeading())
        ->toBe(__('filament-action-approvals::approval.relation_manager.approval_heading', [
            'flow' => __('workbench::workbench.approval_actions.purchase_orders.submit'),
        ]));
});
