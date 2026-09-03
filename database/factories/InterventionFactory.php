<?php

namespace Database\Factories;

use App\Models\Intervention;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Intervention>
 */
class InterventionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'room' => 'Kitchen',
            'performed_on' => now()->subDays($this->faker->numberBetween(1, 14))->toDateString(),
            'description' => $this->faker->randomElement([
                'Placed gel bait under the sink',
                'Sealed the gap behind the dishwasher',
                'Deep-cleaned behind the fridge',
                'Set sticky traps along the baseboard',
            ]),
        ];
    }
}
