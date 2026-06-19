<?php

declare(strict_types=1);

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\PurchaseOrderDetail;

/**
 * @extends Factory<PurchaseOrderDetail>
 */
class PurchaseOrderDetailFactory extends Factory
{
    protected $model = PurchaseOrderDetail::class;

    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'vendor_name' => fake()->company(),
            'reference' => fake()->bothify('REF-####'),
        ];
    }
}
