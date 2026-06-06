<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\ApproverResolvers\UserResolver;
use CoringaWc\FilamentActionApprovals\Enums\StepInstanceStatus;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use CoringaWc\FilamentActionApprovals\Models\Approval;
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
function makeCurrentStepInstanceFlow(): array
{
    $approver = User::factory()->create();

    $flow = ApprovalFlow::create([
        'name' => 'Current Step Instance Flow',
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

it('resolves the waiting step instance from the loaded collection without per-record queries', function (): void {
    $engine = app(ApprovalEngine::class);
    [$flow, $approver] = makeCurrentStepInstanceFlow();

    collect(range(1, 3))->each(function () use ($engine, $flow, $approver): void {
        $order = PurchaseOrder::factory()->create();
        $engine->submit($order, $flow, $approver->getKey(), 'submit');
    });

    $approvals = Approval::query()
        ->with('stepInstances.step')
        ->get();

    DB::enableQueryLog();

    $approvals->each(function (Approval $approval): void {
        expect($approval->currentStepInstance())
            ->toBeInstanceOf(ApprovalStepInstance::class)
            ->and($approval->currentStepInstance()->status)->toBe(StepInstanceStatus::Waiting);
    });

    $stepInstanceLookups = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => preg_match('/from\s+["`]?approval_step_instances["`]?/i', $query['query']) === 1);

    DB::disableQueryLog();

    expect($stepInstanceLookups)->toHaveCount(0);
});

it('falls back to a query for currentStepInstance when stepInstances is not loaded', function (): void {
    $engine = app(ApprovalEngine::class);
    [$flow, $approver] = makeCurrentStepInstanceFlow();

    $order = PurchaseOrder::factory()->create();
    $engine->submit($order, $flow, $approver->getKey(), 'submit');

    $approval = Approval::query()->findOrFail(Approval::query()->value('id'));

    expect($approval->relationLoaded('stepInstances'))->toBeFalse()
        ->and($approval->currentStepInstance())->toBeInstanceOf(ApprovalStepInstance::class)
        ->and($approval->currentStepInstance()->status)->toBe(StepInstanceStatus::Waiting);
});
