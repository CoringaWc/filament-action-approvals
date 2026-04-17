<?php

declare(strict_types=1);

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'amount' => fake()->randomFloat(2, 100, 50000),
            'status' => 'draft',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => 'approved']);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => ['status' => 'rejected']);
    }
}
