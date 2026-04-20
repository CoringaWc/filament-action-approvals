<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Tests\TestCase;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DatabaseSeeder;

it('allows purchase order submission for the creator and configured approvers only', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $requester = User::query()->where('email', 'requester@filament-action-approvals.test')->firstOrFail();
    $manager = User::query()->where('email', 'manager@filament-action-approvals.test')->firstOrFail();
    $director = User::query()->where('email', 'director@filament-action-approvals.test')->firstOrFail();
    $admin = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();

    $purchaseOrder = PurchaseOrder::factory()->for($requester, 'user')->create();

    expect($purchaseOrder->canBeSubmittedForApproval('submit', $requester->getKey()))->toBeTrue()
        ->and($purchaseOrder->canBeSubmittedForApproval('submit', $manager->getKey()))->toBeTrue()
        ->and($purchaseOrder->canBeSubmittedForApproval('submit', $director->getKey()))->toBeTrue()
        ->and($purchaseOrder->canBeSubmittedForApproval('submit', $admin->getKey()))->toBeFalse()
        ->and($purchaseOrder->canBeSubmittedForApproval('cancel', $requester->getKey()))->toBeTrue()
        ->and($purchaseOrder->canBeSubmittedForApproval('cancel', $manager->getKey()))->toBeTrue()
        ->and($purchaseOrder->canBeSubmittedForApproval('cancel', $director->getKey()))->toBeTrue()
        ->and($purchaseOrder->canBeSubmittedForApproval('cancel', $admin->getKey()))->toBeFalse();
});

it('shows purchase order submit actions only to the creator and configured approvers', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $requester = User::query()->where('email', 'requester@filament-action-approvals.test')->firstOrFail();
    $manager = User::query()->where('email', 'manager@filament-action-approvals.test')->firstOrFail();
    $admin = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();

    $purchaseOrder = PurchaseOrder::factory()->for($requester, 'user')->create();

    $test->actingAs($requester);

    Livewire::test(EditPurchaseOrder::class, ['record' => $purchaseOrder->getKey()])
        ->assertActionVisible('submitPO')
        ->assertActionVisible('cancelPO');

    $test->actingAs($manager);

    Livewire::test(EditPurchaseOrder::class, ['record' => $purchaseOrder->getKey()])
        ->assertActionVisible('submitPO')
        ->assertActionVisible('cancelPO');

    $test->actingAs($admin);

    Livewire::test(EditPurchaseOrder::class, ['record' => $purchaseOrder->getKey()])
        ->assertActionHidden('submitPO')
        ->assertActionHidden('cancelPO');
});

it('hides purchase order submit actions after the approval is completed or cancelled', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $engine = app(ApprovalEngine::class);
    $requester = User::query()->where('email', 'requester@filament-action-approvals.test')->firstOrFail();
    $manager = User::query()->where('email', 'manager@filament-action-approvals.test')->firstOrFail();
    $director = User::query()->where('email', 'director@filament-action-approvals.test')->firstOrFail();

    $approvedOrder = PurchaseOrder::factory()->for($requester, 'user')->create();
    $approvedApproval = $engine->submit($approvedOrder, submittedBy: $requester->getKey(), actionKey: 'submit');
    $managerStep = $approvedApproval->currentStepInstance();
    expect($managerStep)->not->toBeNull();
    $engine->approve($managerStep, $manager->getKey());

    $approvedApproval->refresh();
    $directorStep = $approvedApproval->currentStepInstance();
    expect($directorStep)->not->toBeNull();
    $engine->approve($directorStep, $director->getKey());

    $approvedOrder->refresh();

    expect($approvedOrder->canBeSubmittedForApproval('submit', $requester->getKey()))->toBeFalse()
        ->and($approvedOrder->canBeSubmittedForApproval('cancel', $requester->getKey()))->toBeFalse();

    $cancelledOrder = PurchaseOrder::factory()->for($requester, 'user')->create();
    $cancelledApproval = $engine->submit($cancelledOrder, submittedBy: $requester->getKey(), actionKey: 'submit');
    $engine->cancel($cancelledApproval);

    $cancelledOrder->refresh();

    expect($cancelledOrder->canBeSubmittedForApproval('submit', $requester->getKey()))->toBeFalse()
        ->and($cancelledOrder->canBeSubmittedForApproval('cancel', $requester->getKey()))->toBeFalse();

    $test->actingAs($requester);

    Livewire::test(EditPurchaseOrder::class, ['record' => $approvedOrder->getKey()])
        ->assertActionHidden('submitPO')
        ->assertActionHidden('cancelPO');

    Livewire::test(EditPurchaseOrder::class, ['record' => $cancelledOrder->getKey()])
        ->assertActionHidden('submitPO')
        ->assertActionHidden('cancelPO');
});
