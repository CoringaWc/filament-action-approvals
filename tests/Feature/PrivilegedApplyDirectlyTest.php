<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Enums\ActionType;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Events\ApprovalCompleted;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Workbench\App\Models\PurchaseOrder;

beforeEach(function (): void {
    $this->engine = app(ApprovalEngine::class);
});

// ─── canApplyDirectly ────────────────────────────────────────

it('returns false when apply_directly toggle is disabled', function (): void {
    $user = $this->createUser();

    config()->set('filament-action-approvals.privileged.enabled', true);
    config()->set('filament-action-approvals.privileged.user_ids', [$user->getKey()]);
    config()->set('filament-action-approvals.privileged.bypass.apply_directly', false);

    expect(FilamentActionApprovalsPlugin::canApplyDirectly($user->getKey()))->toBeFalse();
});

it('returns true when toggle is on and user is privileged by ID', function (): void {
    $user = $this->createUser();

    config()->set('filament-action-approvals.privileged.enabled', true);
    config()->set('filament-action-approvals.privileged.user_ids', [$user->getKey()]);
    config()->set('filament-action-approvals.privileged.bypass.apply_directly', true);

    expect(FilamentActionApprovalsPlugin::canApplyDirectly($user->getKey()))->toBeTrue();
});

it('returns true when toggle is on and user is privileged by role', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $user = $this->createUser();
    $user->assignRole('super_admin');

    config()->set('filament-action-approvals.privileged.enabled', true);
    config()->set('filament-action-approvals.privileged.roles', ['super_admin']);
    config()->set('filament-action-approvals.privileged.bypass.apply_directly', true);

    expect(FilamentActionApprovalsPlugin::canApplyDirectly($user->getKey()))->toBeTrue();
});

it('returns false when toggle is on but user is not privileged', function (): void {
    $user = $this->createUser();

    config()->set('filament-action-approvals.privileged.enabled', true);
    config()->set('filament-action-approvals.privileged.user_ids', [999]);
    config()->set('filament-action-approvals.privileged.roles', []);
    config()->set('filament-action-approvals.privileged.bypass.apply_directly', true);

    expect(FilamentActionApprovalsPlugin::canApplyDirectly($user->getKey()))->toBeFalse();
});

it('honors the deprecated super_admin alias for privileged identity', function (): void {
    $user = $this->createUser();

    // Privileged block left at package defaults; identity comes from the alias.
    config()->set('filament-action-approvals.super_admin.enabled', true);
    config()->set('filament-action-approvals.super_admin.user_ids', [$user->getKey()]);
    config()->set('filament-action-approvals.privileged.bypass.apply_directly', true);

    expect(FilamentActionApprovalsPlugin::canApplyDirectly($user->getKey()))->toBeTrue();
});

// ─── autoApprove ─────────────────────────────────────────────

it('completes a single-step approval and fires ApprovalCompleted', function (): void {
    Event::fake([ApprovalCompleted::class]);

    $approver = $this->createUser();
    $privileged = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create(['status' => 'draft']);

    $approval = $this->engine->submit($order, $flow, $privileged->getKey());

    $this->engine->autoApprove($approval, $privileged->getKey());

    $approval->refresh();

    expect($approval->status)->toBe(ApprovalStatus::Approved);
    Event::assertDispatched(ApprovalCompleted::class);
});

it('applies the approvable mutation through the completion callback', function (): void {
    $privileged = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$this->createUser()->getKey()]);
    $order = PurchaseOrder::factory()->create(['status' => 'draft']);

    $approval = $this->engine->submit($order, $flow, $privileged->getKey());

    $this->engine->autoApprove($approval, $privileged->getKey());

    expect($order->refresh()->status)->toBe('approved');
});

it('records one approved action per step on a multi-step flow', function (): void {
    $privileged = $this->createUser();
    $flow = $this->createMultiStepFlow(PurchaseOrder::class, [
        ['name' => 'Step 1', 'approver_ids' => [$this->createUser()->getKey()]],
        ['name' => 'Step 2', 'approver_ids' => [$this->createUser()->getKey()]],
    ]);
    $order = PurchaseOrder::factory()->create(['status' => 'draft']);

    $approval = $this->engine->submit($order, $flow, $privileged->getKey());

    $this->engine->autoApprove($approval, $privileged->getKey());

    $approval->refresh();

    expect($approval->status)->toBe(ApprovalStatus::Approved)
        ->and($approval->actions()->where('type', ActionType::Approved)->count())->toBe(2)
        ->and($approval->actions()->where('type', ActionType::Approved)->pluck('user_id')->unique()->all())
        ->toBe([$privileged->getKey()]);
});

it('completes a step that requires multiple approvals with a single action', function (): void {
    $privileged = $this->createUser();
    $flow = $this->createMultiStepFlow(PurchaseOrder::class, [
        ['name' => 'Step 1', 'approver_ids' => [$this->createUser()->getKey(), $this->createUser()->getKey()], 'required' => 2],
    ]);
    $order = PurchaseOrder::factory()->create(['status' => 'draft']);

    $approval = $this->engine->submit($order, $flow, $privileged->getKey());

    $this->engine->autoApprove($approval, $privileged->getKey());

    $approval->refresh();

    expect($approval->status)->toBe(ApprovalStatus::Approved)
        ->and($approval->actions()->where('type', ActionType::Approved)->count())->toBe(1);
});
