<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Spatie\Permission\Models\Role;
use Workbench\App\Models\PurchaseOrder;

beforeEach(function (): void {
    $this->engine = app(ApprovalEngine::class);
});

// ─── isSuperAdmin ────────────────────────────────────────────

it('returns false when super admin is disabled', function (): void {
    config()->set('filament-action-approvals.super_admin.enabled', false);

    $user = $this->createUser();

    expect(FilamentActionApprovalsPlugin::isSuperAdmin($user->getKey()))->toBeFalse();
});

it('returns true when user ID matches super admin list', function (): void {
    $user = $this->createUser();

    config()->set('filament-action-approvals.super_admin.enabled', true);
    config()->set('filament-action-approvals.super_admin.user_ids', [$user->getKey()]);

    expect(FilamentActionApprovalsPlugin::isSuperAdmin($user->getKey()))->toBeTrue();
});

it('returns true when user has super admin role', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $user = $this->createUser();
    $user->assignRole('super_admin');

    config()->set('filament-action-approvals.super_admin.enabled', true);
    config()->set('filament-action-approvals.super_admin.roles', ['super_admin']);
    config()->set('filament-action-approvals.super_admin.user_ids', []);

    expect(FilamentActionApprovalsPlugin::isSuperAdmin($user->getKey()))->toBeTrue();
});

it('returns false when user has no matching role or ID', function (): void {
    $user = $this->createUser();

    config()->set('filament-action-approvals.super_admin.enabled', true);
    config()->set('filament-action-approvals.super_admin.user_ids', [999]);
    config()->set('filament-action-approvals.super_admin.roles', ['some_other_role']);

    expect(FilamentActionApprovalsPlugin::isSuperAdmin($user->getKey()))->toBeFalse();
});

it('returns false for null userId when not authenticated', function (): void {
    config()->set('filament-action-approvals.super_admin.enabled', true);

    expect(FilamentActionApprovalsPlugin::isSuperAdmin(null))->toBeFalse();
});

it('returns false when user does not exist', function (): void {
    config()->set('filament-action-approvals.super_admin.enabled', true);
    config()->set('filament-action-approvals.super_admin.user_ids', []);
    config()->set('filament-action-approvals.super_admin.roles', ['super_admin']);

    expect(FilamentActionApprovalsPlugin::isSuperAdmin(99999))->toBeFalse();
});

// ─── shouldHideSuperAdminsFromSelects ─────────────────────────

it('returns false for hide from selects when disabled', function (): void {
    config()->set('filament-action-approvals.super_admin.enabled', false);

    expect(FilamentActionApprovalsPlugin::shouldHideSuperAdminsFromSelects())->toBeFalse();
});

it('returns true for hide from selects when enabled with default config', function (): void {
    config()->set('filament-action-approvals.super_admin.enabled', true);
    config()->set('filament-action-approvals.super_admin.hide_from_selects', true);

    expect(FilamentActionApprovalsPlugin::shouldHideSuperAdminsFromSelects())->toBeTrue();
});

it('returns false for hide from selects when explicitly disabled', function (): void {
    config()->set('filament-action-approvals.super_admin.enabled', true);
    config()->set('filament-action-approvals.super_admin.hide_from_selects', false);

    expect(FilamentActionApprovalsPlugin::shouldHideSuperAdminsFromSelects())->toBeFalse();
});

// ─── superAdminUserIds / superAdminRoles ──────────────────────

it('returns empty user IDs when feature is disabled', function (): void {
    config()->set('filament-action-approvals.super_admin.enabled', false);
    config()->set('filament-action-approvals.super_admin.user_ids', [1, 2, 3]);

    expect(FilamentActionApprovalsPlugin::superAdminUserIds())->toBe([]);
});

it('returns configured user IDs when feature is enabled', function (): void {
    config()->set('filament-action-approvals.super_admin.enabled', true);
    config()->set('filament-action-approvals.super_admin.hide_from_selects', true);
    config()->set('filament-action-approvals.super_admin.user_ids', [1, 2, 3]);

    expect(FilamentActionApprovalsPlugin::superAdminUserIds())->toBe([1, 2, 3]);
});

it('returns empty roles when hide from selects is false', function (): void {
    config()->set('filament-action-approvals.super_admin.enabled', true);
    config()->set('filament-action-approvals.super_admin.hide_from_selects', false);
    config()->set('filament-action-approvals.super_admin.roles', ['super_admin']);

    expect(FilamentActionApprovalsPlugin::superAdminRoles())->toBe([]);
});

it('returns configured roles when feature is active', function (): void {
    config()->set('filament-action-approvals.super_admin.enabled', true);
    config()->set('filament-action-approvals.super_admin.hide_from_selects', true);
    config()->set('filament-action-approvals.super_admin.roles', ['super_admin']);

    expect(FilamentActionApprovalsPlugin::superAdminRoles())->toBe(['super_admin']);
});

// ─── Super Admin Engine Integration ──────────────────────────

it('engine does not perform authorization — any userId is accepted', function (): void {
    $approver = $this->createUser();
    $nonApprover = $this->createUser();

    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create(['status' => 'draft']);

    $approval = $this->engine->submit($order, $flow, $approver->getKey());
    $stepInstance = $approval->currentStepInstance();
    expect($stepInstance)->not->toBeNull();

    // Engine accepts any userId (authorization is in Filament UI layer)
    $this->engine->approve($stepInstance, $nonApprover->getKey());

    $approval->refresh();
    expect($approval->status)->toBe(ApprovalStatus::Approved);
});

it('canUserAct returns true for assigned approver', function (): void {
    $approver = $this->createUser();

    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create();

    $approval = $this->engine->submit($order, $flow, $approver->getKey());
    $stepInstance = $approval->currentStepInstance();
    expect($stepInstance)->not->toBeNull();

    expect($stepInstance->canUserAct($approver->getKey()))->toBeTrue();
});

it('canUserAct returns false for non-assigned non-delegated user', function (): void {
    $approver = $this->createUser();
    $outsider = $this->createUser();

    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create();

    $approval = $this->engine->submit($order, $flow, $approver->getKey());
    $stepInstance = $approval->currentStepInstance();
    expect($stepInstance)->not->toBeNull();

    expect($stepInstance->canUserAct($outsider->getKey()))->toBeFalse();
});
