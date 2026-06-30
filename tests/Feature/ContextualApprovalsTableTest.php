<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Actions\ListRequesterApprovalsAction;
use CoringaWc\FilamentActionApprovals\Enums\ApprovalStatus;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Tests\TestCase;
use CoringaWc\FilamentActionApprovals\Widgets\ContextualApprovalsTable;
use CoringaWc\FilamentActionApprovals\Widgets\RequesterApprovalsTable;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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

    $otherPendingApproval = Approval::create([
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
    expect(collect($component->instance()->getTable()->getActions())
        ->contains(fn (mixed $action): bool => $action instanceof ActionGroup))->toBeFalse()
        ->and($component->instance()->getTable()->getRecordActionsPosition())->toBe(RecordActionsPosition::BeforeColumns);

    $component
        ->assertSet('tableFilters.status.value', ApprovalStatus::Pending->value)
        ->assertTableActionVisible('view', $pendingApproval)
        ->assertCanSeeTableRecords([$pendingApproval])
        ->assertCanNotSeeTableRecords([$approvedApproval, $otherPendingApproval])
        ->filterTable('status', ApprovalStatus::Approved->value)
        ->assertCanSeeTableRecords([$approvedApproval])
        ->assertCanNotSeeTableRecords([$pendingApproval, $otherPendingApproval]);
});

it('allows the current panel to configure the contextual approvals table and query scope', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $user = User::factory()->create();
    $test->actingAs($user);

    $purchaseOrder = PurchaseOrder::factory()->for($user)->create();

    $flow = ApprovalFlow::create([
        'name' => 'Configured Contextual Approvals Flow',
        'approvable_type' => $purchaseOrder->getMorphClass(),
        'action_key' => 'submit',
        'is_active' => true,
    ]);

    $visibleApproval = Approval::create([
        'approval_flow_id' => $flow->getKey(),
        'approvable_type' => $purchaseOrder->getMorphClass(),
        'approvable_id' => $purchaseOrder->getKey(),
        'status' => ApprovalStatus::Pending,
        'action_key' => 'visible-action',
        'submitted_by' => $user->getKey(),
        'submitted_at' => now(),
    ]);

    $hiddenApproval = Approval::create([
        'approval_flow_id' => $flow->getKey(),
        'approvable_type' => $purchaseOrder->getMorphClass(),
        'approvable_id' => $purchaseOrder->getKey(),
        'status' => ApprovalStatus::Pending,
        'action_key' => 'hidden-action',
        'submitted_by' => $user->getKey(),
        'submitted_at' => now()->subMinute(),
    ]);

    $plugin = FilamentActionApprovalsPlugin::current();

    expect($plugin)->toBeInstanceOf(FilamentActionApprovalsPlugin::class);

    if (! $plugin instanceof FilamentActionApprovalsPlugin) {
        return;
    }

    $configuredContext = false;
    $scopedContext = false;

    $plugin
        ->configureContextualApprovalsTableUsing(function (Table $table, ContextualApprovalsTable $widget) use (&$configuredContext, $purchaseOrder): Table {
            $configuredContext = $widget->approvableType === $purchaseOrder->getMorphClass()
                && $widget->approvableId === (string) $purchaseOrder->getKey()
                && $widget->context === ['scope' => 'purchase-orders'];

            return $table->columns([
                TextColumn::make('action_key')
                    ->label('Configured action'),
            ]);
        })
        ->scopeContextualApprovalsUsing(function (Builder $query, ContextualApprovalsTable $widget) use (&$scopedContext): Builder {
            $scopedContext = filled($widget->approvableType)
                && filled($widget->approvableId)
                && $widget->context === ['scope' => 'purchase-orders'];

            return $query->where('action_key', 'visible-action');
        });

    $component = Livewire::test(ContextualApprovalsTable::class, [
        'approvableType' => $purchaseOrder->getMorphClass(),
        'approvableId' => (string) $purchaseOrder->getKey(),
        'context' => ['scope' => 'purchase-orders'],
    ]);

    expect(array_keys($component->instance()->getTable()->getColumns()))->toBe(['action_key'])
        ->and(array_keys($component->instance()->getTable()->getFilters()))->toBe(['status'])
        ->and($component->instance()->context)->toBe(['scope' => 'purchase-orders'])
        ->and($configuredContext)->toBeTrue()
        ->and($scopedContext)->toBeTrue();

    $component
        ->assertSet('tableFilters.status.value', ApprovalStatus::Pending->value)
        ->assertCanSeeTableRecords([$visibleApproval])
        ->assertCanNotSeeTableRecords([$hiddenApproval]);
});

it('scopes requester approvals table to the current submitter and shows only details as a row action', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $submitter = User::factory()->create();
    $otherSubmitter = User::factory()->create();
    $purchaseOrder = PurchaseOrder::factory()->for($submitter)->create();

    $flow = ApprovalFlow::create([
        'name' => 'Requester Approvals Flow',
        'approvable_type' => $purchaseOrder->getMorphClass(),
        'action_key' => 'submit',
        'is_active' => true,
    ]);

    $visibleApproval = Approval::create([
        'approval_flow_id' => $flow->getKey(),
        'approvable_type' => $purchaseOrder->getMorphClass(),
        'approvable_id' => $purchaseOrder->getKey(),
        'status' => ApprovalStatus::Pending,
        'action_key' => 'submit',
        'submitted_by' => $submitter->getKey(),
        'submitted_by_type' => $submitter->getMorphClass(),
        'submitted_by_id' => $submitter->getKey(),
        'submitted_at' => now(),
    ]);

    $hiddenApproval = Approval::create([
        'approval_flow_id' => $flow->getKey(),
        'approvable_type' => $purchaseOrder->getMorphClass(),
        'approvable_id' => $purchaseOrder->getKey(),
        'status' => ApprovalStatus::Pending,
        'action_key' => 'cancel',
        'submitted_by' => $otherSubmitter->getKey(),
        'submitted_by_type' => $otherSubmitter->getMorphClass(),
        'submitted_by_id' => $otherSubmitter->getKey(),
        'submitted_at' => now()->subMinute(),
    ]);

    $metadataFallbackApproval = Approval::create([
        'approval_flow_id' => $flow->getKey(),
        'approvable_type' => $purchaseOrder->getMorphClass(),
        'approvable_id' => $purchaseOrder->getKey(),
        'status' => ApprovalStatus::Approved,
        'action_key' => 'metadata-review',
        'submitted_by' => null,
        'submitted_by_type' => null,
        'submitted_by_id' => null,
        'submitted_at' => now()->subMinutes(2),
        'completed_at' => now()->subMinute(),
        'metadata' => ['requested_by_user_id' => $submitter->getKey()],
    ]);

    $test->actingAs($submitter);

    $component = Livewire::test(RequesterApprovalsTable::class, [
        'approvableType' => $purchaseOrder->getMorphClass(),
        'approvableId' => (string) $purchaseOrder->getKey(),
    ]);

    expect(array_keys($component->instance()->getTable()->getFilters()))->toBe(['status'])
        ->and(collect($component->instance()->getTable()->getActions())
            ->contains(fn (mixed $action): bool => $action instanceof ActionGroup))->toBeFalse()
        ->and($component->instance()->getTable()->getRecordActionsPosition())->toBe(RecordActionsPosition::BeforeColumns)
        ->and(array_keys($component->instance()->getTable()->getFlatActions()))->toContain('view');

    $component
        ->assertTableActionVisible('view', $visibleApproval)
        ->assertTableActionHidden('approve', $visibleApproval)
        ->assertTableActionHidden('reject', $visibleApproval)
        ->assertTableActionHidden('approvalComment', $visibleApproval)
        ->assertTableActionHidden('delegate', $visibleApproval)
        ->assertCanSeeTableRecords([$visibleApproval, $metadataFallbackApproval])
        ->assertCanNotSeeTableRecords([$hiddenApproval]);
});

it('allows the current panel to scope requester approvals with contextual parameters', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $submitter = User::factory()->create();
    $purchaseOrder = PurchaseOrder::factory()->for($submitter)->create();

    $flow = ApprovalFlow::create([
        'name' => 'Scoped Requester Approvals Flow',
        'approvable_type' => $purchaseOrder->getMorphClass(),
        'action_key' => 'submit',
        'is_active' => true,
    ]);

    $visibleApproval = Approval::create([
        'approval_flow_id' => $flow->getKey(),
        'approvable_type' => $purchaseOrder->getMorphClass(),
        'approvable_id' => $purchaseOrder->getKey(),
        'status' => ApprovalStatus::Pending,
        'action_key' => 'visible-action',
        'submitted_by' => $submitter->getKey(),
        'submitted_by_type' => $submitter->getMorphClass(),
        'submitted_by_id' => $submitter->getKey(),
        'submitted_at' => now(),
    ]);

    $hiddenApproval = Approval::create([
        'approval_flow_id' => $flow->getKey(),
        'approvable_type' => $purchaseOrder->getMorphClass(),
        'approvable_id' => $purchaseOrder->getKey(),
        'status' => ApprovalStatus::Pending,
        'action_key' => 'hidden-action',
        'submitted_by' => $submitter->getKey(),
        'submitted_by_type' => $submitter->getMorphClass(),
        'submitted_by_id' => $submitter->getKey(),
        'submitted_at' => now()->subMinute(),
    ]);

    $plugin = FilamentActionApprovalsPlugin::current();

    expect($plugin)->toBeInstanceOf(FilamentActionApprovalsPlugin::class);

    if (! $plugin instanceof FilamentActionApprovalsPlugin) {
        return;
    }

    /** @var list<array<string, mixed>> $scopedParameters */
    $scopedParameters = [];

    $plugin->scopeRequesterApprovalsUsing(function (Builder $query, array $parameters) use (&$scopedParameters): Builder {
        $scopedParameters[] = $parameters;

        if (($parameters['context']['scope'] ?? null) !== 'purchase-orders') {
            return $query->where('action_key', '__hidden__');
        }

        return $query->where('action_key', 'visible-action');
    });

    $test->actingAs($submitter);

    $unscopedAction = ListRequesterApprovalsAction::make()
        ->forApprovableType($purchaseOrder->getMorphClass());

    $scopedAction = ListRequesterApprovalsAction::make()
        ->forApprovableType($purchaseOrder->getMorphClass())
        ->contextParameters(['scope' => 'purchase-orders']);

    expect($unscopedAction->isHidden())->toBeTrue()
        ->and($scopedAction->isHidden())->toBeFalse();

    $component = Livewire::test(RequesterApprovalsTable::class, [
        'approvableType' => $purchaseOrder->getMorphClass(),
        'context' => ['scope' => 'purchase-orders'],
    ]);

    $requesterTable = $component->instance();

    if (! $requesterTable instanceof RequesterApprovalsTable) {
        throw new LogicException('Expected requester approvals table component.');
    }

    expect($requesterTable->context)->toBe(['scope' => 'purchase-orders'])
        ->and(collect($requesterTable->getTable()->getActions())
            ->contains(fn (mixed $action): bool => $action instanceof ActionGroup))->toBeFalse()
        ->and($requesterTable->getTable()->getRecordActionsPosition())->toBe(RecordActionsPosition::BeforeColumns)
        ->and(collect($scopedParameters)->contains(
            fn (array $parameters): bool => ($parameters['context']['scope'] ?? null) === 'purchase-orders',
        ))->toBeTrue();

    $component
        ->assertTableActionVisible('view', $visibleApproval)
        ->assertCanSeeTableRecords([$visibleApproval])
        ->assertCanNotSeeTableRecords([$hiddenApproval]);

    $plugin->scopeRequesterApprovalsUsing(
        fn (Builder $query, array $_parameters): Builder => $query,
    );
});
