<?php

declare(strict_types=1);

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\User;
use Workbench\App\States\Invoice\AwaitingPayment;
use Workbench\App\States\Invoice\Cancelled;
use Workbench\App\States\Invoice\Issuing;
use Workbench\App\States\Invoice\Paid;
use Workbench\App\States\Invoice\Sent;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'number' => strtoupper(fake()->bothify('INV-####')),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 100, 50000),
            'status' => Issuing::class,
        ];
    }

    public function issuing(): static
    {
        return $this->state(fn (): array => [
            'status' => Issuing::class,
            'previous_status' => null,
            'sent_at' => null,
            'paid_at' => null,
            'cancelled_at' => null,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => Sent::class,
            'previous_status' => Issuing::getMorphClass(),
            'sent_at' => now()->toDateString(),
            'paid_at' => null,
            'cancelled_at' => null,
        ]);
    }

    public function awaitingPayment(): static
    {
        return $this->state(fn (): array => [
            'status' => AwaitingPayment::class,
            'previous_status' => Sent::getMorphClass(),
            'sent_at' => now()->subDay()->toDateString(),
            'paid_at' => null,
            'cancelled_at' => null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => Paid::class,
            'previous_status' => AwaitingPayment::getMorphClass(),
            'sent_at' => now()->subDays(2)->toDateString(),
            'paid_at' => now()->toDateString(),
            'cancelled_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => Cancelled::class,
            'previous_status' => Sent::getMorphClass(),
            'sent_at' => now()->subDays(2)->toDateString(),
            'paid_at' => null,
            'cancelled_at' => now()->toDateString(),
        ]);
    }
}
