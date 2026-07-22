<?php

namespace Database\Factories;

use App\Models\CargoInvoiceLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CargoInvoiceLineFactory extends Factory
{
    protected $model = CargoInvoiceLine::class;

    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 5, 80);

        return [
            'user_id' => User::factory(),
            'store_id' => null,
            'carrier_code' => 'surat',
            'invoice_number' => $this->faker->unique()->numerify('INV-#####'),
            'invoice_serial_number' => $this->faker->unique()->numerify('S-#####'),
            'invoice_date' => $this->faker->dateTimeBetween('-90 days', 'now'),
            'order_number' => null,
            'parcel_unique_id' => $this->faker->unique()->numerify('PKG#####'),
            'cargo_type' => $this->faker->randomElement(['OUTBOUND', 'RETURN']),
            'desi' => $this->faker->randomFloat(2, 0.5, 50),
            'amount' => $amount,
            'vat_amount' => round($amount * 0.18, 2),
            'total_amount' => round($amount * 1.18, 2),
            'currency' => 'TRY',
            'status' => 'pending',
            'is_reconciled' => false,
            'raw_payload' => [],
        ];
    }

    public function outbound(): static
    {
        return $this->state(['cargo_type' => 'OUTBOUND']);
    }

    public function returned(): static
    {
        return $this->state(['cargo_type' => 'RETURN']);
    }

    public function matched(string $orderNumber = null): static
    {
        return $this->state(['order_number' => $orderNumber ?? $this->faker->numerify('#########')]);
    }
}
