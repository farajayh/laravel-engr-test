<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

use App\Models\Claim;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Insurer>
 */
class InsurerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'code' => strtoupper($this->faker->unique()->bothify('INS###')),
            'claim_date_preference' => $this->faker->randomElement(['submission_date', 'encounter_date']),
            'min_batch_size' => $this->faker->numberBetween(1, 3),
            'max_batch_size' => $this->faker->numberBetween(10, 50),
            'specialty' => 'Cardiology',
            'daily_processing_capacity' => $this->faker->numberBetween(1000, 10000),
        ];
    }
}
