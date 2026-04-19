<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\ApproverResolvers\CustomRuleResolver;
use CoringaWc\FilamentActionApprovals\ApproverResolvers\RoleResolver;
use CoringaWc\FilamentActionApprovals\Concerns\HasApprovals;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;
use Workbench\App\Models\Expense;
use Workbench\App\Models\PurchaseOrder;

// ─── RoleResolver ─────────────────────────────────────────────

it('resolves users by single role string (backward compatible)', function (): void {
    Role::findOrCreate('manager', 'web');
    $manager = $this->createUser();
    $manager->assignRole('manager');

    $resolver = new RoleResolver;
    $order = PurchaseOrder::factory()->create();

    $userIds = $resolver->resolve(['role' => 'manager'], $order);

    expect($userIds)->toContain($manager->getKey());
});

it('resolves users by multi-select role array', function (): void {
    Role::findOrCreate('manager', 'web');
    Role::findOrCreate('director', 'web');
    $manager = $this->createUser();
    $manager->assignRole('manager');

    $director = $this->createUser();
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
    $user = $this->createUser();
    $user->assignRole('manager');
    $user->assignRole('director');

    $resolver = new RoleResolver;
    $order = PurchaseOrder::factory()->create();

    $userIds = $resolver->resolve(['role' => ['manager', 'director']], $order);

    expect($userIds)->toContain($user->getKey())
        ->and(array_unique($userIds))->toHaveCount(count($userIds));
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
    $manager = $this->createUser();
    $user = $this->createUser();

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
    $instance = $testModel::query()->find($expense->getKey());

    $resolver = new CustomRuleResolver;
    $userIds = $resolver->resolve(['custom_rule' => 'test_rule'], $instance);

    expect($userIds)->toBe([$manager->getKey()]);
});

it('returns list of int from custom rule closure', function (): void {
    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $expense = Expense::factory()->create(['user_id' => $user1->getKey()]);

    // Create a model class with a deterministic rule returning multiple IDs
    $testModel = new class extends Model
    {
        use HasApprovals;

        protected $table = 'expenses';

        protected $guarded = [];

        /** @var list<int> */
        public static array $returnIds = [];

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
    $instance = $testModel::query()->find($expense->getKey());

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
