<?php

declare(strict_types=1);

use CoringaWc\FilamentAcl\Resources\Permissions\PermissionResource;
use CoringaWc\FilamentActionApprovals\ApproverResolvers\UserResolver;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Pages\ApprovalsDashboard;
use CoringaWc\FilamentActionApprovals\RelationManagers\ApprovalsRelationManager;
use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlows\ApprovalFlowResource;
use CoringaWc\FilamentActionApprovals\Resources\Approvals\ApprovalResource;
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;
use CoringaWc\FilamentActionApprovals\Support\ApprovableModelLabel;
use CoringaWc\FilamentActionApprovals\Tests\TestCase;
use Filament\Actions\ActionGroup;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Workbench\App\Filament\Resources\Expenses\ExpenseResource;
use Workbench\App\Filament\Resources\Invoices\InvoiceResource;
use Workbench\App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use Workbench\App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Workbench\App\Filament\Resources\Users\Pages\CreateUser;
use Workbench\App\Filament\Resources\Users\UserResource;
use Workbench\App\Models\Expense;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DatabaseSeeder;

it('seeds roles, users and purchase order flow for the workbench', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();
    $manager = User::query()->where('email', 'manager@filament-action-approvals.test')->firstOrFail();
    $director = User::query()->where('email', 'director@filament-action-approvals.test')->firstOrFail();
    $requester = User::query()->where('email', 'requester@filament-action-approvals.test')->firstOrFail();

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
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);
    $test->actingAs(User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail());

    $test->get(PurchaseOrderResource::getUrl('create'))
        ->assertOk()
        ->assertSee(__('workbench::workbench.resources.purchase_orders.fields.requester'))
        ->assertSee(__('workbench::workbench.resources.purchase_orders.fields.amount'));

    $test->get(PurchaseOrderResource::getUrl('index'))
        ->assertOk()
        ->assertSeeText('Ver aprovações', false);
});

it('can render expense create page with pt_BR labels', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);
    $test->actingAs(User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail());

    $test->get(ExpenseResource::getUrl('create'))
        ->assertOk()
        ->assertSee(__('workbench::workbench.resources.expenses.fields.requester'))
        ->assertSee(__('workbench::workbench.resources.expenses.fields.category'));

    $test->get(ExpenseResource::getUrl('index'))
        ->assertOk()
        ->assertSeeText('Ver aprovações', false);
});

it('can render grouped approval response actions on workbench resource pages', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();

    $test->actingAs($admin);

    $createFlow = function (Model $model) use ($admin): ApprovalFlow {
        $flow = ApprovalFlow::create([
            'name' => class_basename($model::class).' Approval Flow',
            'approvable_type' => $model->getMorphClass(),
            'is_active' => true,
        ]);

        $flow->steps()->create([
            'name' => 'Step 1',
            'order' => 1,
            'type' => StepType::Single,
            'approver_resolver' => UserResolver::class,
            'approver_config' => ['user_ids' => [$admin->getKey()]],
            'required_approvals' => 1,
        ]);

        return $flow;
    };

    $purchaseOrder = PurchaseOrder::factory()->for($admin, 'user')->create();
    $expense = Expense::factory()->for($admin, 'user')->create();
    $invoice = Invoice::factory()->for($admin, 'user')->issuing()->create();

    app(ApprovalEngine::class)->submit($purchaseOrder, $createFlow($purchaseOrder), $admin->getKey());
    app(ApprovalEngine::class)->submit($expense, $createFlow($expense), $admin->getKey());
    app(ApprovalEngine::class)->submit($invoice, $createFlow($invoice), $admin->getKey());

    $test->get(PurchaseOrderResource::getUrl('edit', ['record' => $purchaseOrder]))
        ->assertOk()
        ->assertSeeText(__('filament-action-approvals::approval.approvals'), false);

    $test->get(ExpenseResource::getUrl('edit', ['record' => $expense]))
        ->assertOk()
        ->assertSeeText(__('filament-action-approvals::approval.approvals'), false);

    $test->get(InvoiceResource::getUrl('view', ['record' => $invoice]))
        ->assertOk()
        ->assertSeeText(__('filament-action-approvals::approval.approvals'), false);
});

it('shows the approval response action group to the manager on purchase order edit when approval is pending', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $manager = User::query()->where('email', 'manager@filament-action-approvals.test')->firstOrFail();
    $requester = User::query()->where('email', 'requester@filament-action-approvals.test')->firstOrFail();

    $purchaseOrder = PurchaseOrder::factory()->for($requester, 'user')->create();

    $flow = ApprovalFlow::query()
        ->where('approvable_type', $purchaseOrder->getMorphClass())
        ->whereNull('action_key')
        ->firstOrFail();

    app(ApprovalEngine::class)->submit($purchaseOrder, $flow, $requester->getKey());

    $test->actingAs($manager);

    /** @var EditPurchaseOrder $page */
    $page = Livewire::test(EditPurchaseOrder::class, ['record' => $purchaseOrder->getKey()])->instance();

    $approvalActionsGroup = collect($page->getCachedHeaderActions())
        ->first(fn (mixed $action): bool => $action instanceof ActionGroup && $action->getLabel() === __('filament-action-approvals::approval.approvals'));

    expect($approvalActionsGroup)->toBeInstanceOf(ActionGroup::class);

    /** @var ActionGroup $approvalActionsGroup */
    expect($approvalActionsGroup->isVisible())->toBeTrue();
});

it('can create a user and assign a role from the workbench', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();
    $managerRole = Role::query()->where('name', 'manager')->firstOrFail();

    $test->actingAs($admin);

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
        ->firstOrFail();

    expect($createdUser)->not->toBeNull()
        ->and($createdUser->hasRole('manager'))->toBeTrue();
});

it('can render permission and approval resources in pt_BR', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);
    $test->actingAs(User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail());

    $test->get(PermissionResource::getUrl('create', configuration: 'filament-acl-permissions'))
        ->assertOk()
        ->assertSee('Permissões');

    $test->get(ApprovalFlowResource::getUrl('index'))
        ->assertOk()
        ->assertSeeText('Aprovação de Pedido de Compra', false)
        ->assertSeeText('Pedido de Compra', false)
        ->assertSeeText('Aprovação para envio de fatura', false)
        ->assertSeeText('Fatura', false)
        ->assertSeeText('Aprovação para envio de despesa', false)
        ->assertSeeText('Despesa', false);

    $test->get(ApprovalResource::getUrl('index'))
        ->assertOk()
        ->assertSeeText('Aprovações', false)
        ->assertSeeText('Pendentes', false)
        ->assertSeeText('Aprovadas', false)
        ->assertSeeText('Rejeitadas', false);
});

it('can render approvals dashboard in pt_BR', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);
    $test->actingAs(User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail());

    $test->get(ApprovalsDashboard::getUrl(panel: 'admin'))
        ->assertOk()
        ->assertSeeText('Dashboard de Aprovações', false)
        ->assertSeeText('5d', false)
        ->assertSeeText('15d', false)
        ->assertSeeText('30d', false)
        ->assertSeeText('Todos', false);
});

it('can render approval resource with contextual record scope in the workbench', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();

    $test->actingAs($admin);

    $flow = ApprovalFlow::create([
        'name' => 'Workbench Contextual Approval Flow',
        'approvable_type' => (new PurchaseOrder)->getMorphClass(),
        'is_active' => true,
    ]);
    $flow->steps()->create([
        'name' => 'Step 1',
        'order' => 1,
        'type' => StepType::Single,
        'approver_resolver' => UserResolver::class,
        'approver_config' => ['user_ids' => [$admin->getKey()]],
        'required_approvals' => 1,
    ]);
    $visibleOrder = PurchaseOrder::factory()->for($admin, 'user')->create();
    $hiddenOrder = PurchaseOrder::factory()->for($admin, 'user')->create();

    app(ApprovalEngine::class)->submit($visibleOrder, $flow, $admin->getKey());
    app(ApprovalEngine::class)->submit($hiddenOrder, $flow, $admin->getKey());

    $visibleLabel = ApprovableModelLabel::resolveWithKey($visibleOrder->getMorphClass(), $visibleOrder->getKey());
    $hiddenLabel = ApprovableModelLabel::resolveWithKey($hiddenOrder->getMorphClass(), $hiddenOrder->getKey());

    $test->get(ApprovalResource::getUrl('index', [
        'approvableType' => $visibleOrder->getMorphClass(),
        'approvableId' => $visibleOrder->getKey(),
    ]))
        ->assertOk()
        ->assertSeeText(__('filament-action-approvals::approval.actions.clear_context'), false)
        ->assertSeeText(__('filament-action-approvals::approval.approval_context.record_scope', [
            'record' => $visibleLabel,
        ]), false)
        ->assertSeeText($visibleLabel, false)
        ->assertDontSeeText($hiddenLabel, false);
});

it('shows the submitted action instead of the flow name in the approvals relation manager', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail();

    $test->actingAs($admin);

    $flow = ApprovalFlow::create([
        'name' => 'Relation Manager Flow Name',
        'approvable_type' => (new PurchaseOrder)->getMorphClass(),
        'is_active' => true,
    ]);
    $flow->steps()->create([
        'name' => 'Step 1',
        'order' => 1,
        'type' => StepType::Single,
        'approver_resolver' => UserResolver::class,
        'approver_config' => ['user_ids' => [$admin->getKey()]],
        'required_approvals' => 1,
    ]);

    $purchaseOrder = PurchaseOrder::factory()->for($admin, 'user')->create();

    app(ApprovalEngine::class)->submit($purchaseOrder, $flow, $admin->getKey(), 'submit');

    Livewire::test(ApprovalsRelationManager::class, [
        'ownerRecord' => $purchaseOrder,
        'pageClass' => EditPurchaseOrder::class,
    ])
        ->assertSeeText(__('workbench::workbench.approval_actions.purchase_orders.submit'), false)
        ->assertDontSeeText('Relation Manager Flow Name', false);
});

it('can render user, invoice and expense resources in pt_BR', function (): void {
    /** @var TestCase $test */
    $test = $this;

    $test->seed(DatabaseSeeder::class);
    $test->actingAs(User::query()->where('email', 'admin@filament-action-approvals.test')->firstOrFail());

    $test->get(UserResource::getUrl('index'))
        ->assertOk()
        ->assertSeeText('Usuários', false)
        ->assertSeeText('Funções e Permissões', false);

    $test->get(InvoiceResource::getUrl('index'))
        ->assertOk()
        ->assertSeeText('Faturas', false)
        ->assertSeeText('Em emissão', false)
        ->assertSeeText('Ver aprovações', false);

    $test->get(ExpenseResource::getUrl('index'))
        ->assertOk()
        ->assertSeeText('Despesas', false)
        ->assertSeeText('Rascunho', false)
        ->assertSeeText('Ver aprovações', false);
});
