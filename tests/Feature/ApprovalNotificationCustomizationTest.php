<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Support\ApprovableModelLabel;
use Filament\Notifications\DatabaseNotification;
use Workbench\App\Models\Expense;
use Workbench\App\Models\PurchaseOrder;

it('uses a custom record label in the rejection notification body when provided by the approvable model', function (): void {
    $approver = $this->createUser();
    $submitter = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->for($submitter)->create(['status' => 'draft']);

    $approval = app(ApprovalEngine::class)->submit($order, $flow, $submitter->getKey());
    $stepInstance = $approval->currentStepInstance();

    expect($stepInstance)->not->toBeNull();

    app(ApprovalEngine::class)->reject($stepInstance, $approver->getKey(), 'Rejected');

    /** @var DatabaseNotification $notification */
    $notification = $submitter->notifications()->latest()->firstOrFail();

    $expectedBody = __('filament-action-approvals::approval.notifications.rejected_body', [
        'model' => ApprovableModelLabel::resolve($order),
        'id' => 'PO-'.$order->getKey(),
    ]);

    $fallbackBody = __('filament-action-approvals::approval.notifications.rejected_body', [
        'model' => ApprovableModelLabel::resolve($order),
        'id' => $order->getKey(),
    ]);

    expect($notification->data['body'])
        ->toBe($expectedBody)
        ->not->toBe($fallbackBody);
});

it('adds a custom record action to the rejection notification when provided by the approvable model', function (): void {
    $approver = $this->createUser();
    $submitter = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->for($submitter)->create(['status' => 'draft']);

    $approval = app(ApprovalEngine::class)->submit($order, $flow, $submitter->getKey());
    $stepInstance = $approval->currentStepInstance();

    expect($stepInstance)->not->toBeNull();

    app(ApprovalEngine::class)->reject($stepInstance, $approver->getKey(), 'Rejected');

    /** @var DatabaseNotification $notification */
    $notification = $submitter->notifications()->latest()->firstOrFail();
    $actions = $notification->data['actions'] ?? [];

    expect($actions)->toHaveCount(1)
        ->and($actions[0]['label'] ?? null)->toBe('View purchase order')
        ->and($actions[0]['url'] ?? null)->toBe('/admin/purchase-orders/'.$order->getKey());
});

it('keeps the default record key in the rejection notification body when the approvable model does not customize it', function (): void {
    $approver = $this->createUser();
    $submitter = $this->createUser();
    $flow = $this->createSingleStepFlow(Expense::class, [$approver->getKey()]);
    $expense = Expense::factory()->for($submitter)->create(['status' => 'draft']);

    $approval = app(ApprovalEngine::class)->submit($expense, $flow, $submitter->getKey());
    $stepInstance = $approval->currentStepInstance();

    expect($stepInstance)->not->toBeNull();

    app(ApprovalEngine::class)->reject($stepInstance, $approver->getKey(), 'Rejected');

    /** @var DatabaseNotification $notification */
    $notification = $submitter->notifications()->latest()->firstOrFail();

    expect($notification->data['body'])->toBe(__('filament-action-approvals::approval.notifications.rejected_body', [
        'model' => ApprovableModelLabel::resolve($expense),
        'id' => $expense->getKey(),
    ]));
});
