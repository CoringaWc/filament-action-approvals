<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Actions\ListApprovalsAction;
use CoringaWc\FilamentActionApprovals\ApproverResolvers\UserResolver;
use CoringaWc\FilamentActionApprovals\Concerns\HasApprovalsResource;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Resources\Approvals\Tables\ApprovalsTable;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Tests\TestCase;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;

class ApprovalActionsTestResource
{
    use HasApprovalsResource;
}

class ApprovalTableActionsTestTable extends ApprovalsTable
{
    /**
     * @return array<int, Action|ActionGroup>
     */
    public static function getRecordActionsForTesting(): array
    {
        return static::recordActions();
    }
}

it('groups approval header actions under a single approvals action group', function (): void {
    $actions = ApprovalActionsTestResource::getApprovalHeaderActions();

    expect($actions)->toHaveCount(1)
        ->and($actions[0])->toBeInstanceOf(ActionGroup::class)
        ->and($actions[0]->getLabel())->toBe(__('filament-action-approvals::approval.approvals'))
        ->and($actions[0]->getIcon())->toBe(Heroicon::EllipsisVertical);
});

it('groups approval response header actions under a single approvals action group', function (): void {
    $actions = ApprovalActionsTestResource::getApprovalResponseHeaderActions();

    expect($actions)->toHaveCount(1)
        ->and($actions[0])->toBeInstanceOf(ActionGroup::class)
        ->and($actions[0]->getLabel())->toBe(__('filament-action-approvals::approval.approvals'))
        ->and($actions[0]->getIcon())->toBe(Heroicon::EllipsisVertical);
});

it('opens contextual approvals in a slide over instead of redirecting', function (): void {
    $action = ListApprovalsAction::make()
        ->forApprovableType((new PurchaseOrder)->getMorphClass());

    expect($action->getUrl())->toBeNull()
        ->and($action->isModalSlideOver())->toBeTrue();
});

it('groups approval resource record actions by default', function (): void {
    $actions = ApprovalTableActionsTestTable::getRecordActionsForTesting();

    expect($actions)->toHaveCount(1)
        ->and($actions[0])->toBeInstanceOf(ActionGroup::class);

    /** @var ActionGroup $actionGroup */
    $actionGroup = $actions[0];

    expect(array_keys($actionGroup->getFlatActions()))->toBe([
        'view',
        'approve',
        'reject',
        'approvalComment',
        'delegate',
    ]);
});

it('shows grouped operational actions before any table action is mounted', function (): void {
    config()->set('filament-action-approvals.actions.comment', true);
    config()->set('filament-action-approvals.actions.delegate', true);

    /** @var TestCase $test */
    $test = $this;

    $approver = User::factory()->create();

    $test->actingAs($approver);

    $flow = ApprovalFlow::create([
        'name' => 'Test Flow',
        'approvable_type' => (new PurchaseOrder)->getMorphClass(),
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

    $order = PurchaseOrder::factory()->create();
    $approval = app(ApprovalEngine::class)->submit($order, $flow, $approver->getKey());

    $actions = ApprovalTableActionsTestTable::getRecordActionsForTesting();

    expect($actions[0])->toBeInstanceOf(ActionGroup::class);

    /** @var ActionGroup $actionGroup */
    $actionGroup = $actions[0]->getClone()->record($approval);

    $visibleActionNames = collect($actionGroup->getActions())
        ->filter(fn (Action|ActionGroup $action): bool => $action instanceof Action && (! $action->isHidden()))
        ->map(fn (Action $action): string => $action->getName() ?? '')
        ->values()
        ->all();

    expect($visibleActionNames)->toBe([
        'view',
        'approve',
        'reject',
        'approvalComment',
        'delegate',
    ]);
});
