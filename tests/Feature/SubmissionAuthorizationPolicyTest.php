<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Actions\SubmitForApprovalAction;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Tests\TestCase;
use Workbench\App\Models\Expense;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;

class ActionAwarePurchaseOrder extends PurchaseOrder
{
    protected $table = 'purchase_orders';

    public function canSubmitForApproval(?string $actionKey = null, int|string|null $userId = null): bool
    {
        return $actionKey === 'submit'
            && (int) $this->user_id === (int) ($userId ?? auth()->id());
    }
}

it('passes the action key into the model submission policy', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $test->actingAs($owner);

    $order = ActionAwarePurchaseOrder::query()->create([
        'user_id' => $owner->getKey(),
        'title' => 'PO-Action-Aware',
        'description' => 'Test order',
        'amount' => 1500,
        'status' => 'draft',
    ]);

    expect($order->canBeSubmittedForApproval('submit', $owner->getKey()))->toBeTrue()
        ->and($order->canBeSubmittedForApproval('cancel', $owner->getKey()))->toBeFalse()
        ->and($order->canBeSubmittedForApproval('submit', $otherUser->getKey()))->toBeFalse()
        ->and($order->canBeSubmittedForApproval(null, $owner->getKey()))->toBeFalse();
});

it('uses the action-aware submission policy in locked submit buttons', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $owner = User::factory()->create();

    $test->actingAs($owner);

    $order = ActionAwarePurchaseOrder::query()->create([
        'user_id' => $owner->getKey(),
        'title' => 'PO-Action-Buttons',
        'description' => 'Test order',
        'amount' => 2200,
        'status' => 'draft',
    ]);

    ApprovalFlow::create([
        'name' => 'Submit Flow',
        'approvable_type' => $order->getMorphClass(),
        'action_key' => 'submit',
        'is_active' => true,
    ]);

    ApprovalFlow::create([
        'name' => 'Cancel Flow',
        'approvable_type' => $order->getMorphClass(),
        'action_key' => 'cancel',
        'is_active' => true,
    ]);

    $genericAction = SubmitForApprovalAction::make('generic')
        ->record($order);

    $submitAction = SubmitForApprovalAction::make('submitPO')
        ->actionKey('submit')
        ->record($order);

    $cancelAction = SubmitForApprovalAction::make('cancelPO')
        ->actionKey('cancel')
        ->record($order);

    expect($genericAction->isHidden())->toBeFalse()
        ->and($submitAction->isHidden())->toBeFalse()
        ->and($cancelAction->isHidden())->toBeTrue();
});

it('allows only configured flow approvers by default', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $approver = User::factory()->create();
    $outsider = User::factory()->create();

    $test->actingAs($approver);

    $expense = Expense::factory()->create();

    $test->createSingleStepFlow(Expense::class, [$approver->getKey()], 'reimburse');

    expect($expense->canBeSubmittedForApproval('reimburse', $approver->getKey()))->toBeTrue()
        ->and($expense->canBeSubmittedForApproval('reimburse', $outsider->getKey()))->toBeFalse()
        ->and($expense->canBeSubmittedForApproval('submit', $approver->getKey()))->toBeFalse();
});
