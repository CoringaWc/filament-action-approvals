<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Enums\ActionType;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Support\ApprovalPayloadDiff;
use CoringaWc\FilamentActionApprovals\Tests\TestCase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DatabaseSeeder;

it('intercepts native edit actions and submits changed allowlisted fields for approval', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $submitter = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();
    $approver = User::query()->where('email', 'manager@filament-action-approvals.test')->firstOrFail();

    $test->actingAs($submitter);

    $test->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()], 'purchase-order.edit');

    $order = PurchaseOrder::factory()->for($submitter, 'user')->create([
        'title' => 'Original order',
        'description' => 'Safe description',
        'amount' => 1200,
    ]);

    Livewire::test(ListPurchaseOrders::class)
        ->callTableAction('edit', $order, [
            'user_id' => $submitter->getKey(),
            'title' => 'Updated order',
            'description' => 'Reviewer note with CPF 123.456.789-09',
            'amount' => 1500,
        ])
        ->assertNotified(__('filament-action-approvals::approval.actions.approval_request_submitted'));

    $order->refresh();

    expect($order->title)->toBe('Original order')
        ->and($order->amount)->toEqual('1200.00')
        ->and($order->approvals()->count())->toBe(1);

    $approval = $order->approvals()->firstOrFail();

    expect($approval->status)->toBe(ApprovalStatus::Pending)
        ->and($approval->submittedActionKey())->toBe('purchase-order.edit')
        ->and(data_get($approval->metadata, 'crud.operation'))->toBe('edit')
        ->and(data_get($approval->metadata, 'payload.title'))->toBe('Updated order')
        ->and(data_get($approval->metadata, 'payload.amount'))->toBe(1500)
        ->and(data_get($approval->metadata, 'payload.description'))->toBeNull();

    expect(ApprovalPayloadDiff::forApproval($approval))->toHaveCount(2);

    app(ApprovalEngine::class)->approve($approval->currentStepInstance(), $approver->getKey());

    expect($order->refresh()->title)->toBe('Updated order')
        ->and($order->amount)->toEqual('1500.00')
        ->and(data_get($approval->refresh()->metadata, 'crud.applied_at'))->not->toBeNull()
        ->and(data_get($approval->metadata, 'applied_at'))->not->toBeNull();
});

it('intercepts native delete actions and only deletes after approval is completed', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $submitter = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();
    $approver = User::query()->where('email', 'manager@filament-action-approvals.test')->firstOrFail();

    $test->actingAs($submitter);

    $test->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()], 'purchase-order.delete');

    $order = PurchaseOrder::factory()->for($submitter, 'user')->create();
    $orderKey = $order->getKey();

    Livewire::test(ListPurchaseOrders::class)
        ->callTableAction('delete', $order)
        ->assertNotified();

    expect(PurchaseOrder::query()->find($orderKey))->toBeInstanceOf(PurchaseOrder::class)
        ->and($order->approvals()->count())->toBe(1);

    $approval = $order->approvals()->firstOrFail();

    expect($approval->submittedActionKey())->toBe('purchase-order.delete')
        ->and(data_get($approval->metadata, 'crud.operation'))->toBe('delete');

    app(ApprovalEngine::class)->approve($approval->currentStepInstance(), $approver->getKey());

    expect(PurchaseOrder::query()->find($orderKey))->toBeNull()
        ->and(data_get($approval->refresh()->metadata, 'crud.applied_at'))->not->toBeNull()
        ->and(data_get($approval->metadata, 'applied_at'))->not->toBeNull();
});

it('safe fails intercepted edit actions when no approval flow exists', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $submitter = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();

    $test->actingAs($submitter);

    $order = PurchaseOrder::factory()->for($submitter, 'user')->create([
        'title' => 'Original order',
        'amount' => 1200,
    ]);

    Livewire::test(ListPurchaseOrders::class)
        ->callTableAction('edit', $order, [
            'user_id' => $submitter->getKey(),
            'title' => 'Updated order',
            'description' => $order->getAttribute('description'),
            'amount' => 1500,
        ])
        ->assertNotified(__('filament-action-approvals::approval.actions.approval_flow_missing'));

    expect($order->refresh()->getAttribute('title'))->toBe('Original order')
        ->and($order->approvals()->count())->toBe(0);
});

it('safe fails intercepted delete actions when no approval flow exists', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $submitter = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();

    $test->actingAs($submitter);

    $order = PurchaseOrder::factory()->for($submitter, 'user')->create();
    $orderKey = $order->getKey();

    Livewire::test(ListPurchaseOrders::class)
        ->callTableAction('delete', $order)
        ->assertNotified(__('filament-action-approvals::approval.actions.approval_flow_missing'));

    expect(PurchaseOrder::query()->find($orderKey))->toBeInstanceOf(PurchaseOrder::class)
        ->and($order->approvals()->count())->toBe(0);
});

it('safe fails intercepted edit actions when no allowlisted field changed', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $submitter = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();
    $approver = User::query()->where('email', 'manager@filament-action-approvals.test')->firstOrFail();

    $test->actingAs($submitter);

    $test->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()], 'purchase-order.edit');

    $order = PurchaseOrder::factory()->for($submitter, 'user')->create([
        'title' => 'Original order',
        'description' => 'Safe description',
        'amount' => 1200,
    ]);

    Livewire::test(ListPurchaseOrders::class)
        ->callTableAction('edit', $order, [
            'user_id' => $submitter->getKey(),
            'title' => $order->getAttribute('title'),
            'description' => $order->getAttribute('description'),
            'amount' => $order->getAttribute('amount'),
        ])
        ->assertNotified(__('filament-action-approvals::approval.actions.no_changes_to_approve'));

    expect($order->approvals()->count())->toBe(0);
});

it('safe fails intercepted edit actions when a matching approval is already pending', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $submitter = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();
    $approver = User::query()->where('email', 'manager@filament-action-approvals.test')->firstOrFail();

    $test->actingAs($submitter);

    $test->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()], 'purchase-order.edit');

    $order = PurchaseOrder::factory()->for($submitter, 'user')->create([
        'title' => 'Original order',
        'description' => 'Safe description',
        'amount' => 1200,
    ]);

    Livewire::test(ListPurchaseOrders::class)
        ->callTableAction('edit', $order, [
            'user_id' => $submitter->getKey(),
            'title' => 'First update',
            'description' => $order->getAttribute('description'),
            'amount' => 1500,
        ])
        ->assertNotified(__('filament-action-approvals::approval.actions.approval_request_submitted'));

    Livewire::test(ListPurchaseOrders::class)
        ->callTableAction('edit', $order, [
            'user_id' => $submitter->getKey(),
            'title' => 'Second update',
            'description' => $order->getAttribute('description'),
            'amount' => 1600,
        ])
        ->assertNotified(__('filament-action-approvals::approval.actions.pending_request_exists'));

    expect($order->refresh()->getAttribute('title'))->toBe('Original order')
        ->and($order->approvals()->count())->toBe(1);
});

it('keeps intercepted crud approvals pending when the target disappears before approval', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $submitter = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();
    $approver = User::query()->where('email', 'manager@filament-action-approvals.test')->firstOrFail();

    $test->actingAs($submitter);

    $test->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()], 'purchase-order.edit');

    $order = PurchaseOrder::factory()->for($submitter, 'user')->create([
        'title' => 'Original order',
        'amount' => 1200,
    ]);

    Livewire::test(ListPurchaseOrders::class)
        ->callTableAction('edit', $order, [
            'user_id' => $submitter->getKey(),
            'title' => 'Updated order',
            'description' => $order->description,
            'amount' => 1500,
        ])
        ->assertNotified(__('filament-action-approvals::approval.actions.approval_request_submitted'));

    $approval = $order->approvals()->firstOrFail();
    $order->delete();

    expect(fn (): null => app(ApprovalEngine::class)->approve($approval->currentStepInstance(), $approver->getKey()))
        ->toThrow(ValidationException::class);

    expect($approval->refresh()->status)->toBe(ApprovalStatus::Pending)
        ->and($approval->completed_at)->toBeNull()
        ->and(data_get($approval->metadata, 'crud.applied_at'))->toBeNull()
        ->and(data_get($approval->metadata, 'applied_at'))->toBeNull()
        ->and($approval->actions()->where('type', ActionType::Approved)->count())->toBe(0);
});

it('keeps intercepted crud approvals pending when the target cannot apply crud operations', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    $target = User::factory()->create(['name' => 'Original user']);

    $flow = $test->createSingleStepFlow(User::class, [$approver->getKey()]);
    $approval = app(ApprovalEngine::class)->submit($target, $flow, $submitter->getKey());
    $approval->forceFill([
        'metadata' => [
            'crud' => ['operation' => 'edit'],
            'payload' => ['name' => 'Updated user'],
        ],
    ])->save();

    expect(fn (): null => app(ApprovalEngine::class)->approve($approval->currentStepInstance(), $approver->getKey()))
        ->toThrow(ValidationException::class);

    expect($approval->refresh()->status)->toBe(ApprovalStatus::Pending)
        ->and($target->refresh()->name)->toBe('Original user')
        ->and(data_get($approval->metadata, 'crud.applied_at'))->toBeNull()
        ->and(data_get($approval->metadata, 'applied_at'))->toBeNull()
        ->and($approval->actions()->where('type', ActionType::Approved)->count())->toBe(0);
});

it('lets privileged users apply intercepted crud actions directly without approval records', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $privileged = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();
    $approver = User::query()->where('email', 'manager@filament-action-approvals.test')->firstOrFail();

    config()->set('filament-action-approvals.privileged.enabled', true);
    config()->set('filament-action-approvals.privileged.user_ids', [$privileged->getKey()]);
    config()->set('filament-action-approvals.privileged.bypass.apply_directly', true);

    $test->actingAs($privileged);

    $test->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()], 'purchase-order.edit');
    $test->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()], 'purchase-order.delete');

    $order = PurchaseOrder::factory()->for($privileged, 'user')->create([
        'title' => 'Direct order',
        'amount' => 900,
    ]);

    Livewire::test(ListPurchaseOrders::class)
        ->callTableAction('edit', $order, [
            'user_id' => $privileged->getKey(),
            'title' => 'Directly updated order',
            'description' => $order->description,
            'amount' => 950,
        ])
        ->assertNotified(__('filament-actions::edit.single.notifications.saved.title'));

    expect($order->refresh()->title)->toBe('Directly updated order')
        ->and($order->approvals()->count())->toBe(0);

    $orderKey = $order->getKey();

    Livewire::test(ListPurchaseOrders::class)
        ->callTableAction('delete', $order)
        ->assertNotified(__('filament-actions::delete.single.notifications.deleted.title'));

    expect(PurchaseOrder::query()->find($orderKey))->toBeNull()
        ->and($order->approvals()->count())->toBe(0);
});
