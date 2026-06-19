<?php

declare(strict_types=1);

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\PurchaseOrderLine;

/**
 * @extends Factory<PurchaseOrderLine>
 */
class PurchaseOrderLineFactory extends Factory
{
    protected $model = PurchaseOrderLine::class;

    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'sku' => fake()->bothify('SKU-####'),
            'quantity' => fake()->numberBetween(1, 10),
        ];
    }
}
