<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\ApproverResolvers\UserResolver;
use CoringaWc\FilamentActionApprovals\Concerns\InteractsWithApprovalState;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;

it('refreshes the current record pending approval relation', function (): void {
    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    $flow = approvalStateSingleStepFlow([$approver->getKey()]);
    $order = PurchaseOrder::factory()->for($submitter, 'user')->create();

    $approval = app(ApprovalEngine::class)->submit($order, $flow, $submitter->getKey());

    $page = new class($order)
    {
        use InteractsWithApprovalState {
            currentApprovalForApprovalState as public currentApproval;
        }

        public function __construct(public PurchaseOrder $record) {}

        public function getRecord(): mixed
        {
            return $this->record;
        }

        /**
         * @param  MorphOne<Approval, PurchaseOrder>  $relation
         * @return MorphOne<Approval, PurchaseOrder>
         */
        protected function scopePendingApprovalForApprovalState(MorphOne $relation): MorphOne
        {
            return $relation->with('stepInstances.step');
        }
    };

    $page->refreshApprovalState();

    expect($order->relationLoaded('pendingApproval'))->toBeTrue()
        ->and($page->currentApproval())->toBeInstanceOf(Approval::class)
        ->and($page->currentApproval()?->is($approval))->toBeTrue()
        ->and($page->currentApproval()?->relationLoaded('stepInstances'))->toBeTrue();
});

/**
 * @param  array<int, int|string>  $approverIds
 */
function approvalStateSingleStepFlow(array $approverIds): ApprovalFlow
{
    $flow = ApprovalFlow::create([
        'name' => 'Approval State Flow',
        'approvable_type' => (new PurchaseOrder)->getMorphClass(),
        'is_active' => true,
    ]);

    $flow->steps()->create([
        'name' => 'Step 1',
        'order' => 1,
        'type' => StepType::Single,
        'approver_resolver' => UserResolver::class,
        'approver_config' => ['user_ids' => $approverIds],
        'required_approvals' => 1,
    ]);

    return $flow;
}
