<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\ApproverResolvers\UserResolver;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Resources\Approvals\Pages\ListApprovals;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Tests\TestCase;
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
