<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Tests\Feature\Workbench;

use CoringaWc\FilamentAcl\Resources\Permissions\PermissionResource;
use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlowResource;
use CoringaWc\FilamentActionApprovals\Tests\TestCase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Workbench\App\Filament\Resources\Invoices\InvoiceResource;
use Workbench\App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Workbench\App\Filament\Resources\Users\Pages\CreateUser;
use Workbench\App\Filament\Resources\Users\UserResource;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DatabaseSeeder;

class FilamentWorkbenchSmokeTest extends TestCase
{
    public function test_it_seeds_roles_users_and_purchase_order_flow_for_the_workbench(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@filament-action-approvals.test')->first();
        $manager = User::query()->where('email', 'manager@filament-action-approvals.test')->first();
        $director = User::query()->where('email', 'director@filament-action-approvals.test')->first();
        $requester = User::query()->where('email', 'requester@filament-action-approvals.test')->first();

        self::assertNotNull($admin);
        self::assertNotNull($manager);
        self::assertNotNull($director);
        self::assertNotNull($requester);

        self::assertTrue($admin->hasRole('super_admin'));
        self::assertTrue($manager->hasRole('manager'));
        self::assertTrue($director->hasRole('director'));
        self::assertTrue($requester->hasRole('requester'));
    }

    public function test_it_can_render_the_purchase_order_create_page_with_pt_br_labels(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail());

        $this->get(PurchaseOrderResource::getUrl('create'))
            ->assertOk()
            ->assertSee(__('workbench::workbench.resources.purchase_orders.fields.requester'))
            ->assertSee(__('workbench::workbench.resources.purchase_orders.fields.amount'));
    }

    public function test_it_can_create_a_user_and_assign_a_role_from_the_workbench(): void
    {
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

        self::assertNotNull($createdUser);
        self::assertTrue($createdUser->hasRole('manager'));
    }

    public function test_it_can_render_roles_permissions_and_approval_flow_resources_in_pt_br(): void
    {
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
            ->assertSeeText('Fatura', false);

        $this->get(UserResource::getUrl('index'))
            ->assertOk()
            ->assertSeeText('Usuários', false)
            ->assertSeeText('Funções e Permissões', false);

        $this->get(InvoiceResource::getUrl('index'))
            ->assertOk()
            ->assertSeeText('Faturas', false)
            ->assertSeeText('Em emissão', false);
    }
}
