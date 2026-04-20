<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Tests\TestCase;
use CoringaWc\FilamentActionApprovals\Widgets\ContextualApprovalsTable;
use Livewire\Livewire;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;

it('overrides shared filters in the contextual approvals table and defaults status to pending', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $user = User::factory()->create();
    $test->actingAs($user);

    $purchaseOrder = PurchaseOrder::factory()->for($user)->create();
    $otherPurchaseOrder = PurchaseOrder::factory()->for($user)->create();

    $flow = ApprovalFlow::create([
        'name' => 'Contextual Approvals Flow',
        'approvable_type' => $purchaseOrder->getMorphClass(),
        'action_key' => 'submit',
        'is_active' => true,
    ]);

    $pendingApproval = Approval::create([
        'approval_flow_id' => $flow->getKey(),
        'approvable_type' => $purchaseOrder->getMorphClass(),
        'approvable_id' => $purchaseOrder->getKey(),
        'status' => ApprovalStatus::Pending,
        'submitted_by' => $user->getKey(),
        'submitted_at' => now(),
    ]);

    $approvedApproval = Approval::create([
        'approval_flow_id' => $flow->getKey(),
        'approvable_type' => $purchaseOrder->getMorphClass(),
        'approvable_id' => $purchaseOrder->getKey(),
        'status' => ApprovalStatus::Approved,
        'submitted_by' => $user->getKey(),
        'submitted_at' => now()->subMinute(),
        'completed_at' => now(),
    ]);

    Approval::create([
        'approval_flow_id' => $flow->getKey(),
        'approvable_type' => $otherPurchaseOrder->getMorphClass(),
        'approvable_id' => $otherPurchaseOrder->getKey(),
        'status' => ApprovalStatus::Pending,
        'submitted_by' => $user->getKey(),
        'submitted_at' => now()->subMinutes(2),
    ]);

    $component = Livewire::test(ContextualApprovalsTable::class, [
        'approvableType' => $purchaseOrder->getMorphClass(),
        'approvableId' => (string) $purchaseOrder->getKey(),
    ]);

    expect(array_keys($component->instance()->getTable()->getFilters()))->toBe(['status']);

    $component
        ->assertSet('tableFilters.status.value', ApprovalStatus::Pending->value)
        ->assertCanSeeTableRecords([$pendingApproval])
        ->assertCanNotSeeTableRecords([$approvedApproval]);
});
