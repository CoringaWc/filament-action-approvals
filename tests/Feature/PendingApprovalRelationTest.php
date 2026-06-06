<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\ApproverResolvers\UserResolver;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Illuminate\Support\Facades\DB;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;

/**
 * Builds an active single-step flow and returns it together with the approver.
 *
 * @return array{0: ApprovalFlow, 1: User}
 */
function makePendingApprovalFlow(): array
{
    $approver = User::factory()->create();

    $flow = ApprovalFlow::create([
        'name' => 'Pending Relation Flow',
        'approvable_type' => (new PurchaseOrder)->getMorphClass(),
        'action_key' => 'submit',
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

    return [$flow, $approver];
}

it('exposes the latest pending approval through the pendingApproval relation', function (): void {
    $engine = app(ApprovalEngine::class);
    [$flow, $approver] = makePendingApprovalFlow();

    $order = PurchaseOrder::factory()->create();
    $pending = $engine->submit($order, $flow, $approver->getKey(), 'submit');

    $order->load('pendingApproval');

    expect($order->getRelation('pendingApproval'))->not->toBeNull()
        ->and($order->getRelation('pendingApproval')->is($pending))->toBeTrue();
});

it('returns null pendingApproval once the approval is no longer pending', function (): void {
    $engine = app(ApprovalEngine::class);
    [$flow, $approver] = makePendingApprovalFlow();

    $order = PurchaseOrder::factory()->create();
    $approval = $engine->submit($order, $flow, $approver->getKey(), 'submit');

    $step = $approval->currentStepInstance();
    expect($step)->toBeInstanceOf(ApprovalStepInstance::class);

    if (! $step instanceof ApprovalStepInstance) {
        throw new RuntimeException('Expected current step instance to exist.');
    }

    $engine->approve($step, $approver->getKey());

    $order->load('pendingApproval');

    expect($order->getRelation('pendingApproval'))->toBeNull();
});

it('makes currentApproval relation-aware so eager loading avoids per-record queries', function (): void {
    $engine = app(ApprovalEngine::class);
    [$flow, $approver] = makePendingApprovalFlow();

    $orders = collect(range(1, 3))->map(function () use ($engine, $flow, $approver): PurchaseOrder {
        $order = PurchaseOrder::factory()->create();
        $engine->submit($order, $flow, $approver->getKey(), 'submit');

        return $order;
    });

    $loaded = PurchaseOrder::query()
        ->whereIn('id', $orders->map->getKey())
        ->with('pendingApproval')
        ->get();

    DB::enableQueryLog();

    $loaded->each(function (PurchaseOrder $order): void {
        expect($order->currentApproval())->not->toBeNull();
    });

    $approvalLookups = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => preg_match('/from\s+["`]?approvals["`]?/i', $query['query']) === 1);

    DB::disableQueryLog();

    expect($approvalLookups)->toHaveCount(0);
});

it('falls back to a query for currentApproval when pendingApproval is not loaded', function (): void {
    $engine = app(ApprovalEngine::class);
    [$flow, $approver] = makePendingApprovalFlow();

    $order = PurchaseOrder::factory()->create();
    $pending = $engine->submit($order, $flow, $approver->getKey(), 'submit');

    $fresh = PurchaseOrder::query()->findOrFail($order->getKey());

    expect($fresh->relationLoaded('pendingApproval'))->toBeFalse()
        ->and($fresh->currentApproval()?->is($pending))->toBeTrue();
});
