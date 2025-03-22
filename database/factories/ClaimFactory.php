<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

use App\Models\Claim;
use App\Models\Insurer;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Claim>
 */
class ClaimFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $insurer = Insurer::factory()->create();
        $items =  [
            ['name' => 'item one', 'unit_price' => $this->faker->numberBetween(1, 500), 'quantity' => $this->faker->numberBetween(1, 2)],
            ['name' => 'item two', 'unit_price' => $this->faker->numberBetween(1, 500), 'quantity' => $this->faker->numberBetween(1, 2)],
        ];

        $total_amount = collect($items)->sum(fn($item) => $item['unit_price'] * $item['quantity']);
        return [
            'insurer_code'   => $insurer->code,
            'provider_name'  => $this->faker->company,
            'encounter_date' => $this->faker->date,
            'specialty'      => $insurer->specialty,
            'priority_level' => $this->faker->numberBetween(1, 5),
            'total_amount'   => $total_amount,
            'is_processed'   => false,
            'items' => json_encode($items),
        ];
    }
}
