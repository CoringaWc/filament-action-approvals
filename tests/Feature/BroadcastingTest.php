<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Concerns\BroadcastsConditionally;
use CoringaWc\FilamentActionApprovals\Events\ApprovalCancelled;
use CoringaWc\FilamentActionApprovals\Events\ApprovalCommented;
use CoringaWc\FilamentActionApprovals\Events\ApprovalCompleted;
use CoringaWc\FilamentActionApprovals\Events\ApprovalDelegated;
use CoringaWc\FilamentActionApprovals\Events\ApprovalEscalated;
use CoringaWc\FilamentActionApprovals\Events\ApprovalRejected;
use CoringaWc\FilamentActionApprovals\Events\ApprovalStepCompleted;
use CoringaWc\FilamentActionApprovals\Events\ApprovalSubmitted;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\PurchaseOrder;

dataset('broadcast_config_keys', fn (): array => [
    'submitted' => ['submitted', ApprovalSubmitted::class],
    'approved' => ['approved', ApprovalCompleted::class],
    'rejected' => ['rejected', ApprovalRejected::class],
    'cancelled' => ['cancelled', ApprovalCancelled::class],
    'commented' => ['commented', ApprovalCommented::class],
    'delegated' => ['delegated', ApprovalDelegated::class],
    'step_completed' => ['step_completed', ApprovalStepCompleted::class],
    'escalated' => ['escalated', ApprovalEscalated::class],
]);

it('all events implement ShouldBroadcast and BroadcastsConditionally', function (string $configKey, string $eventClass): void {
    expect(in_array(ShouldBroadcast::class, class_implements($eventClass) ?: []))->toBeTrue()
        ->and(in_array(BroadcastsConditionally::class, class_uses_recursive($eventClass)))->toBeTrue();
})->with('broadcast_config_keys');

it('disables broadcasting by default', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create();
    $approval = app(ApprovalEngine::class)->submit($order, $flow, $approver->getKey());

    config()->set('filament-action-approvals.broadcasting.events.submitted', false);

    $event = new ApprovalSubmitted($approval);

    expect($event->broadcastWhen())->toBeFalse();
});

it('enables broadcasting when config is true', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create();
    $approval = app(ApprovalEngine::class)->submit($order, $flow, $approver->getKey());

    config()->set('filament-action-approvals.broadcasting.events.submitted', true);

    $event = new ApprovalSubmitted($approval);

    expect($event->broadcastWhen())->toBeTrue();
});

it('broadcasts on the approval-events channel', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create();
    $approval = app(ApprovalEngine::class)->submit($order, $flow, $approver->getKey());

    $event = new ApprovalSubmitted($approval);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(Channel::class)
        ->and($channels[0]->name)->toBe('approval-events');
});

it('returns null broadcast queue by default', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create();
    $approval = app(ApprovalEngine::class)->submit($order, $flow, $approver->getKey());

    config()->set('filament-action-approvals.broadcasting.queue', null);

    $event = new ApprovalSubmitted($approval);

    expect($event->broadcastQueue())->toBeNull();
});

it('returns configured broadcast queue', function (): void {
    $approver = $this->createUser();
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);
    $order = PurchaseOrder::factory()->create();
    $approval = app(ApprovalEngine::class)->submit($order, $flow, $approver->getKey());

    config()->set('filament-action-approvals.broadcasting.queue', 'high-priority');

    $event = new ApprovalSubmitted($approval);

    expect($event->broadcastQueue())->toBe('high-priority');
});

it('each event has a unique broadcast config key', function (): void {
    $eventClasses = [
        ApprovalSubmitted::class,
        ApprovalCompleted::class,
        ApprovalRejected::class,
        ApprovalCancelled::class,
        ApprovalCommented::class,
        ApprovalDelegated::class,
        ApprovalStepCompleted::class,
        ApprovalEscalated::class,
    ];

    $keys = [];
    foreach ($eventClasses as $eventClass) {
        $method = new ReflectionMethod($eventClass, 'broadcastConfigKey');
        $method->setAccessible(true);

        // Create instance without constructor to just test the config key method
        $instance = (new ReflectionClass($eventClass))->newInstanceWithoutConstructor();
        $keys[] = $method->invoke($instance);
    }

    expect($keys)->toHaveCount(8)
        ->and(array_unique($keys))->toHaveCount(8);
});
