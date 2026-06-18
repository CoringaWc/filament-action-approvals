<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Support\PrivilegedUserAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Workbench\App\Models\PurchaseOrder;

beforeEach(function (): void {
    $this->engine = app(ApprovalEngine::class);
});

function countQueriesFor(Closure $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $callback();

    $queryCount = count(DB::getQueryLog());

    DB::disableQueryLog();

    return $queryCount;
}

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

it('memoizes privileged role checks within the current request lifecycle', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $user = $this->createUser();
    $user->assignRole('super_admin');

    config()->set('filament-action-approvals.super_admin.enabled', true);
    config()->set('filament-action-approvals.super_admin.roles', ['super_admin']);
    config()->set('filament-action-approvals.super_admin.user_ids', []);

    $firstQueryCount = countQueriesFor(fn (): bool => FilamentActionApprovalsPlugin::isSuperAdmin($user->getKey()));
    $secondQueryCount = countQueriesFor(function () use ($user): void {
        foreach (range(1, 5) as $ignored) {
            expect(FilamentActionApprovalsPlugin::isSuperAdmin($user->getKey()))->toBeTrue();
        }
    });

    expect($firstQueryCount)->toBeGreaterThan(0)
        ->and($secondQueryCount)->toBe(0);
});

it('keeps privileged role check cache isolated per user', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $superAdmin = $this->createUser();
    $regularUser = $this->createUser();
    $superAdmin->assignRole('super_admin');

    config()->set('filament-action-approvals.super_admin.enabled', true);
    config()->set('filament-action-approvals.super_admin.roles', ['super_admin']);
    config()->set('filament-action-approvals.super_admin.user_ids', []);

    expect(FilamentActionApprovalsPlugin::isSuperAdmin($superAdmin->getKey()))->toBeTrue()
        ->and(FilamentActionApprovalsPlugin::isSuperAdmin($regularUser->getKey()))->toBeFalse();
});

it('can flush cached privileged role checks after role mutations', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $user = $this->createUser();

    config()->set('filament-action-approvals.super_admin.enabled', true);
    config()->set('filament-action-approvals.super_admin.roles', ['super_admin']);
    config()->set('filament-action-approvals.super_admin.user_ids', []);

    expect(FilamentActionApprovalsPlugin::isSuperAdmin($user->getKey()))->toBeFalse();

    $user->assignRole('super_admin');
    app(PrivilegedUserAccess::class)->flush();

    expect(FilamentActionApprovalsPlugin::isSuperAdmin($user->getKey()))->toBeTrue();
});

it('does not share privileged role checks across scoped lifecycles', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $user = $this->createUser();

    config()->set('filament-action-approvals.super_admin.enabled', true);
    config()->set('filament-action-approvals.super_admin.roles', ['super_admin']);
    config()->set('filament-action-approvals.super_admin.user_ids', []);

    expect(FilamentActionApprovalsPlugin::isSuperAdmin($user->getKey()))->toBeFalse();

    $user->assignRole('super_admin');
    app()->forgetScopedInstances();

    expect(FilamentActionApprovalsPlugin::isSuperAdmin($user->getKey()))->toBeTrue();
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

it('engine enforces approver authorization for approval actions', function (): void {
    $approver = $this->createUser();
    $nonApprover = $this->createUser();

    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create(['status' => 'draft']);

    $approval = $this->engine->submit($order, $flow, $approver->getKey());
    $stepInstance = $approval->currentStepInstance();
    expect($stepInstance)->not->toBeNull();

    expect(fn () => $this->engine->approve($stepInstance, $nonApprover->getKey()))
        ->toThrow(AuthorizationException::class);

    $approval->refresh();
    expect($approval->status)->toBe(ApprovalStatus::Pending);
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
