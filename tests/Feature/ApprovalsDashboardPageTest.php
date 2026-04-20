<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Pages\ApprovalsDashboard;
use CoringaWc\FilamentActionApprovals\Tests\TestCase;
use Livewire\Livewire;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DatabaseSeeder;

it('applies dashboard quick filters in one click and clears custom dates', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();

    $test->actingAs($admin);

    $startDate = now()->subDays(2)->toDateString();
    $endDate = now()->toDateString();

    Livewire::test(ApprovalsDashboard::class)
        ->assertSet('filters.period', '30d')
        ->assertActionHasColor('last30Days', 'primary')
        ->callAction('filter', [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ])
        ->assertSet('filters.startDate', $startDate)
        ->assertSet('filters.endDate', $endDate)
        ->callAction('last5Days')
        ->assertSet('filters.period', '5d')
        ->assertSet('filters.startDate', null)
        ->assertSet('filters.endDate', null)
        ->assertActionHasColor('last5Days', 'primary')
        ->assertActionHasColor('last30Days', 'gray');
});

it('shows only custom date fields in the dashboard advanced filter action', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();

    $test->actingAs($admin);

    Livewire::test(ApprovalsDashboard::class)
        ->mountAction('filter')
        ->assertSchemaComponentDoesNotExist('period', 'mountedActionSchema0')
        ->assertSchemaComponentExists('startDate', 'mountedActionSchema0')
        ->assertSchemaComponentExists('endDate', 'mountedActionSchema0');
});
