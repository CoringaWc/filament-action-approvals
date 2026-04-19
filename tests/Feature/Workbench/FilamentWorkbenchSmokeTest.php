<?php

declare(strict_types=1);

use CoringaWc\FilamentAcl\Resources\Permissions\PermissionResource;
use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlowResource;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Workbench\App\Filament\Resources\Expenses\ExpenseResource;
use Workbench\App\Filament\Resources\Invoices\InvoiceResource;
use Workbench\App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Workbench\App\Filament\Resources\Users\Pages\CreateUser;
use Workbench\App\Filament\Resources\Users\UserResource;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DatabaseSeeder;

it('seeds roles, users and purchase order flow for the workbench', function (): void {
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@filament-action-approvals.test')->first();
    $manager = User::query()->where('email', 'manager@filament-action-approvals.test')->first();
    $director = User::query()->where('email', 'director@filament-action-approvals.test')->first();
    $requester = User::query()->where('email', 'requester@filament-action-approvals.test')->first();

    expect($admin)->not->toBeNull()
        ->and($manager)->not->toBeNull()
        ->and($director)->not->toBeNull()
        ->and($requester)->not->toBeNull()
        ->and($admin->hasRole('super_admin'))->toBeTrue()
        ->and($manager->hasRole('manager'))->toBeTrue()
        ->and($director->hasRole('director'))->toBeTrue()
        ->and($requester->hasRole('requester'))->toBeTrue();
});

it('can render purchase order create page with pt_BR labels', function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail());

    $this->get(PurchaseOrderResource::getUrl('create'))
        ->assertOk()
        ->assertSee(__('workbench::workbench.resources.purchase_orders.fields.requester'))
        ->assertSee(__('workbench::workbench.resources.purchase_orders.fields.amount'));
});

it('can render expense create page with pt_BR labels', function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail());

    $this->get(ExpenseResource::getUrl('create'))
        ->assertOk()
        ->assertSee(__('workbench::workbench.resources.expenses.fields.requester'))
        ->assertSee(__('workbench::workbench.resources.expenses.fields.category'));
});

it('can create a user and assign a role from the workbench', function (): void {
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();
    $managerRole = Role::query()->where('name', 'manager')->firstOrFail();

    $this->actingAs($admin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Novo Usuário',
            'email' => 'novo.usuario@filament-action-approvals.test',
            'password' => 'password',
            'roles' => [$managerRole->getKey()],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $createdUser = User::query()
        ->where('email', 'novo.usuario@filament-action-approvals.test')
        ->first();

    expect($createdUser)->not->toBeNull()
        ->and($createdUser->hasRole('manager'))->toBeTrue();
});

it('can render roles, permissions and approval flow resources in pt_BR', function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail());

    $this->get(PermissionResource::getUrl('create', configuration: 'filament-acl-permissions'))
        ->assertOk()
        ->assertSee('Permissões');

    $this->get(ApprovalFlowResource::getUrl('index'))
        ->assertOk()
        ->assertSeeText('Aprovação de Pedido de Compra', false)
        ->assertSeeText('Pedido de Compra', false)
        ->assertSeeText('Aprovação para envio de fatura', false)
        ->assertSeeText('Fatura', false)
        ->assertSeeText('Aprovação para envio de despesa', false)
        ->assertSeeText('Despesa', false);

    $this->get(UserResource::getUrl('index'))
        ->assertOk()
        ->assertSeeText('Usuários', false)
        ->assertSeeText('Funções e Permissões', false);

    $this->get(InvoiceResource::getUrl('index'))
        ->assertOk()
        ->assertSeeText('Faturas', false)
        ->assertSeeText('Em emissão', false);

    $this->get(ExpenseResource::getUrl('index'))
        ->assertOk()
        ->assertSeeText('Despesas', false)
        ->assertSeeText('Rascunho', false);
});
