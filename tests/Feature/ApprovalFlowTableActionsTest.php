<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlows\Pages\ListApprovalFlows;
use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlows\Tables\ApprovalFlowsTable;
use CoringaWc\FilamentActionApprovals\Tests\TestCase;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Livewire;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DatabaseSeeder;

it('shows edit and delete table actions for approval flows', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();

    $test->actingAs($admin);

    $flow = ApprovalFlow::create([
        'name' => 'Flow With Actions',
        'approvable_type' => (new PurchaseOrder())->getMorphClass(),
        'is_active' => true,
    ]);

    $component = Livewire::test(ListApprovalFlows::class)
        ->assertCanSeeTableRecords([$flow]);

    /** @var ListApprovalFlows&HasTable $livewire */
    $livewire = $component->instance();

    $resolveActionNames = static fn (array $actions): array => array_values(array_filter(array_map(
        static fn (Action|ActionGroup $action): ?string => $action instanceof Action ? $action->getName() : null,
        $actions,
    )));

    expect($resolveActionNames(
        Table::make($livewire)
            ->recordActions([Action::make('test')])
            ->getRecordActions(),
    ))
        ->toBe(['test']);

    expect($resolveActionNames(
        ApprovalFlowsTable::configure(Table::make($livewire))->getRecordActions(),
    ))
        ->toBe(['edit', 'delete']);

    expect($resolveActionNames(
        $livewire->getTable()->getRecordActions(),
    ))
        ->toBe(['edit', 'delete']);
});
