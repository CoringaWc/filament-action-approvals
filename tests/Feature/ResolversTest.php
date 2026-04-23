<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\ApproverResolvers\CustomRuleResolver;
use CoringaWc\FilamentActionApprovals\ApproverResolvers\RoleResolver;
use CoringaWc\FilamentActionApprovals\Concerns\HasApprovals;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Workbench\App\Models\Expense;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;

// ─── RoleResolver ─────────────────────────────────────────────

it('resolves users by single role string (backward compatible)', function (): void {
    Role::findOrCreate('manager', 'web');
    $manager = User::factory()->create();
    $manager->assignRole('manager');

    $resolver = new RoleResolver;
    $order = PurchaseOrder::factory()->create();

    $userIds = $resolver->resolve(['role' => 'manager'], $order);

    expect($userIds)->toContain($manager->getKey());
});

it('resolves users by multi-select role array', function (): void {
    Role::findOrCreate('manager', 'web');
    Role::findOrCreate('director', 'web');
    $manager = User::factory()->create();
    $manager->assignRole('manager');

    $director = User::factory()->create();
    $director->assignRole('director');

    $resolver = new RoleResolver;
    $order = PurchaseOrder::factory()->create();

    $userIds = $resolver->resolve(['role' => ['manager', 'director']], $order);

    expect($userIds)
        ->toContain($manager->getKey())
        ->toContain($director->getKey());
});

it('returns empty for null role config', function (): void {
    $resolver = new RoleResolver;
    $order = PurchaseOrder::factory()->create();

    expect($resolver->resolve(['role' => null], $order))->toBe([]);
});

it('returns empty for empty role array', function (): void {
    $resolver = new RoleResolver;
    $order = PurchaseOrder::factory()->create();

    expect($resolver->resolve(['role' => []], $order))->toBe([]);
});

it('does not return duplicate user IDs across roles', function (): void {
    Role::findOrCreate('manager', 'web');
    Role::findOrCreate('director', 'web');
    $user = User::factory()->create();
    $user->assignRole('manager');
    $user->assignRole('director');

    $resolver = new RoleResolver;
    $order = PurchaseOrder::factory()->create();

    $userIds = $resolver->resolve(['role' => ['manager', 'director']], $order);

    expect($userIds)->toContain($user->getKey())
        ->and(array_unique($userIds))->toHaveCount(count($userIds));
});

it('limits role select options to the current panel when the roles table supports panel scoping', function (): void {
    Schema::table('roles', function (Blueprint $table): void {
        $table->string('panel')->nullable();
    });

    DB::table('roles')->insert([
        ['name' => 'Admin Role', 'guard_name' => 'web', 'panel' => 'admin'],
        ['name' => 'App Role', 'guard_name' => 'web', 'panel' => 'app'],
    ]);

    $fields = RoleResolver::configSchema();
    /** @var Select $select */
    $select = $fields[0];
    $options = $select->getOptions();

    expect($options)->toHaveKey('Admin Role');
    expect(array_key_exists('App Role', $options))->toBeFalse();
});

it('can disable role panel scoping through config', function (): void {
    config()->set('filament-action-approvals.roles.limit_to_current_panel', false);

    Schema::table('roles', function (Blueprint $table): void {
        $table->string('panel')->nullable();
    });

    DB::table('roles')->insert([
        ['name' => 'Admin Role', 'guard_name' => 'web', 'panel' => 'admin'],
        ['name' => 'App Role', 'guard_name' => 'web', 'panel' => 'app'],
    ]);

    $fields = RoleResolver::configSchema();
    /** @var Select $select */
    $select = $fields[0];
    $options = $select->getOptions();

    expect($options)->toHaveKey('Admin Role');
    expect($options)->toHaveKey('App Role');
});

// ─── CustomRuleResolver ───────────────────────────────────────

it('reports unavailable when model has no approvalCustomRules', function (): void {
    expect(CustomRuleResolver::isAvailable(PurchaseOrder::class))->toBeFalse();
});

it('reports available when model defines approvalCustomRules', function (): void {
    expect(CustomRuleResolver::isAvailable(Expense::class))->toBeTrue();
});

it('reports unavailable when no model class is provided', function (): void {
    expect(CustomRuleResolver::isAvailable(null))->toBeFalse();
});

it('resolves users from model custom rule', function (): void {
    $manager = User::factory()->create();
    $user = User::factory()->create();

    // Temporarily override the Expense custom rules to use a deterministic closure
    $expense = Expense::factory()->create(['user_id' => $user->getKey()]);

    // We test with a fresh resolver using a rule that returns known IDs
    // The 'expense_manager' rule depends on manager_id column which doesn't exist,
    // so we create an anonymous class to prove the resolver works end-to-end
    $testModel = new class extends Model
    {
        use HasApprovals;

        protected $table = 'expenses';

        protected $guarded = [];

        public static int $testManagerId = 0;

        /** @return array<string, callable(self): array<int, int>> */
        public static function approvalCustomRules(): array
        {
            return [
                'test_rule' => function (self $model): array {
                    return [self::$testManagerId];
                },
            ];
        }
    };

    $testModel::$testManagerId = $manager->getKey();
    $instance = $testModel::query()->findOrFail($expense->getKey());

    if (! $instance instanceof Model) {
        throw new RuntimeException('Expected a model instance for the custom rule test.');
    }

    $resolver = new CustomRuleResolver;
    $userIds = $resolver->resolve(['custom_rule' => 'test_rule'], $instance);

    expect($userIds)->toBe([$manager->getKey()]);
});

it('returns list of int from custom rule closure', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $expense = Expense::factory()->create(['user_id' => $user1->getKey()]);

    // Create a model class with a deterministic rule returning multiple IDs
    $testModel = new class extends Model
    {
        use HasApprovals;

        protected $table = 'expenses';

        protected $guarded = [];

        /** @var list<int> */
        public static array $returnIds = [];

        /** @return array<string, callable(self): list<int>> */
        public static function approvalCustomRules(): array
        {
            return [
                'multi_user' => function (self $model): array {
                    return self::$returnIds;
                },
            ];
        }
    };

    $testModel::$returnIds = [$user1->getKey(), $user2->getKey()];
    $instance = $testModel::query()->findOrFail($expense->getKey());

    if (! $instance instanceof Model) {
        throw new RuntimeException('Expected a model instance for the multi user custom rule test.');
    }

    $resolver = new CustomRuleResolver;
    $userIds = $resolver->resolve(['custom_rule' => 'multi_user'], $instance);

    expect($userIds)
        ->toBeArray()
        ->toHaveCount(2)
        ->toContain($user1->getKey())
        ->toContain($user2->getKey());

    // Verify all values are integers
    foreach ($userIds as $id) {
        expect($id)->toBeInt();
    }
});

it('returns empty for unregistered custom rule key', function (): void {
    $resolver = new CustomRuleResolver;
    $expense = Expense::factory()->create();

    $userIds = $resolver->resolve(['custom_rule' => 'nonexistent_rule'], $expense);

    expect($userIds)->toBe([]);
});

it('returns empty for model without approvalCustomRules method', function (): void {
    $resolver = new CustomRuleResolver;
    $order = PurchaseOrder::factory()->create();

    $userIds = $resolver->resolve(['custom_rule' => 'expense_manager'], $order);

    expect($userIds)->toBe([]);
});
