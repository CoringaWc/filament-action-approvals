<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use CoringaWc\FilamentAcl\Support\Utils;
use CoringaWc\FilamentActionApprovals\ApproverResolvers\RoleResolver;
use CoringaWc\FilamentActionApprovals\Enums\EscalationAction;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;
use Workbench\App\States\Invoice\Issuing;
use Workbench\App\States\Invoice\Sent;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('filament-acl:sync', [
            '--panel' => ['admin'],
            '--with-protected-role' => true,
        ]);

        $superAdminRole = Role::findOrCreate(Utils::getProtectedRoleName(), 'web');
        $managerRole = Role::findOrCreate('manager', 'web');
        $directorRole = Role::findOrCreate('director', 'web');
        $requesterRole = Role::findOrCreate('requester', 'web');

        $admin = User::factory()->create([
            'name' => __('workbench::workbench.seeds.users.admin.name'),
            'email' => 'admin@filament-action-approvals.test',
        ]);
        $admin->syncRoles([$superAdminRole]);

        $manager = User::factory()->create([
            'name' => __('workbench::workbench.seeds.users.manager.name'),
            'email' => 'manager@filament-action-approvals.test',
        ]);
        $manager->syncRoles([$managerRole]);

        $director = User::factory()->create([
            'name' => __('workbench::workbench.seeds.users.director.name'),
            'email' => 'director@filament-action-approvals.test',
        ]);
        $director->syncRoles([$directorRole]);

        $requester = User::factory()->create([
            'name' => __('workbench::workbench.seeds.users.requester.name'),
            'email' => 'requester@filament-action-approvals.test',
        ]);
        $requester->syncRoles([$requesterRole]);

        $flow = ApprovalFlow::create([
            'name' => __('workbench::workbench.seeds.flows.purchase_order.name'),
            'description' => __('workbench::workbench.seeds.flows.purchase_order.description'),
            'approvable_type' => (new PurchaseOrder)->getMorphClass(),
            'is_active' => true,
        ]);

        $flow->steps()->create([
            'name' => __('workbench::workbench.seeds.flows.purchase_order.manager_step'),
            'order' => 1,
            'type' => StepType::Single,
            'approver_resolver' => RoleResolver::class,
            'approver_config' => ['role' => $managerRole->name],
            'required_approvals' => 1,
            'sla_hours' => 24,
            'escalation_action' => EscalationAction::Notify,
        ]);

        $flow->steps()->create([
            'name' => __('workbench::workbench.seeds.flows.purchase_order.director_step'),
            'order' => 2,
            'type' => StepType::Single,
            'approver_resolver' => RoleResolver::class,
            'approver_config' => ['role' => $directorRole->name],
            'required_approvals' => 1,
            'sla_hours' => 48,
            'escalation_action' => EscalationAction::AutoApprove,
        ]);

        $invoiceFlow = ApprovalFlow::create([
            'name' => __('workbench::workbench.seeds.flows.invoice_send.name'),
            'description' => __('workbench::workbench.seeds.flows.invoice_send.description'),
            'approvable_type' => (new Invoice)->getMorphClass(),
            'action_key' => Invoice::stateApprovalActionKey(Issuing::class, Sent::class),
            'is_active' => true,
        ]);

        $invoiceFlow->steps()->create([
            'name' => __('workbench::workbench.seeds.flows.invoice_send.manager_step'),
            'order' => 1,
            'type' => StepType::Single,
            'approver_resolver' => RoleResolver::class,
            'approver_config' => ['role' => $managerRole->name],
            'required_approvals' => 1,
            'sla_hours' => 24,
            'escalation_action' => EscalationAction::Notify,
        ]);

        PurchaseOrder::factory()
            ->count(5)
            ->for($requester)
            ->create();

        Invoice::factory()
            ->for($requester)
            ->issuing()
            ->create([
                'number' => 'INV-0001',
                'title' => __('workbench::workbench.seeds.invoices.issuing.title'),
            ]);

        Invoice::factory()
            ->for($requester)
            ->sent()
            ->create([
                'number' => 'INV-0002',
                'title' => __('workbench::workbench.seeds.invoices.sent.title'),
            ]);

        Invoice::factory()
            ->for($requester)
            ->awaitingPayment()
            ->create([
                'number' => 'INV-0003',
                'title' => __('workbench::workbench.seeds.invoices.awaiting_payment.title'),
            ]);
    }
}
