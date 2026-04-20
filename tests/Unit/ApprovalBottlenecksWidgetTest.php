<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Widgets\ApprovalBottlenecksWidget;
use Illuminate\Database\Eloquent\Builder;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;

it('builds the bottlenecks query without relying on having aliases', function (): void {
    $approver = User::factory()->create();

    /** @var ApprovalFlow $flow */
    $flow = $this->createSingleStepFlow(PurchaseOrder::class, [$approver->getKey()]);

    $order = PurchaseOrder::factory()->create();
    $approval = app(ApprovalEngine::class)->submit($order, $flow, $approver->getKey());
    $stepInstance = $approval->currentStepInstance();

    expect($stepInstance)->not->toBeNull();

    $stepInstance?->forceFill([
        'sla_deadline' => now()->subHour(),
    ])->save();

    $widget = new class extends ApprovalBottlenecksWidget
    {
        /**
         * @return Builder<ApprovalFlow>
         */
        public function query(): Builder
        {
            return $this->getTableQuery();
        }
    };

    $rows = $widget->query()->get();

    expect($rows)->toHaveCount(1)
        ->and((int) $rows->first()->pending_approvals_count)->toBe(1)
        ->and((int) $rows->first()->overdue_approvals_count)->toBe(1);
});
