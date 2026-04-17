<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use CoringaWc\FilamentActionApprovals\ApproverResolvers\UserResolver;
use CoringaWc\FilamentActionApprovals\Enums\EscalationAction;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use Illuminate\Database\Seeder;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create users
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $manager = User::factory()->create([
            'name' => 'Manager User',
            'email' => 'manager@example.com',
        ]);

        $director = User::factory()->create([
            'name' => 'Director User',
            'email' => 'director@example.com',
        ]);

        $requester = User::factory()->create([
            'name' => 'Requester User',
            'email' => 'requester@example.com',
        ]);

        // Create approval flow for Purchase Orders
        $flow = ApprovalFlow::create([
            'name' => 'Purchase Order Approval',
            'description' => 'Two-step approval for purchase orders: manager then director.',
            'approvable_type' => (new PurchaseOrder)->getMorphClass(),
            'is_active' => true,
        ]);

        $flow->steps()->create([
            'name' => 'Manager Approval',
            'order' => 1,
            'type' => StepType::Single,
            'approver_resolver' => UserResolver::class,
            'approver_config' => ['user_ids' => [$manager->getKey()]],
            'required_approvals' => 1,
            'sla_hours' => 24,
            'escalation_action' => EscalationAction::Notify,
        ]);

        $flow->steps()->create([
            'name' => 'Director Approval',
            'order' => 2,
            'type' => StepType::Single,
            'approver_resolver' => UserResolver::class,
            'approver_config' => ['user_ids' => [$director->getKey()]],
            'required_approvals' => 1,
            'sla_hours' => 48,
            'escalation_action' => EscalationAction::AutoApprove,
        ]);

        // Create sample purchase orders
        PurchaseOrder::factory()
            ->count(5)
            ->for($requester)
            ->create();
    }
}
