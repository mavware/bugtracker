<?php

namespace Database\Factories;

use App\Models\Intervention;
use App\Models\Room;
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
            'performed_on' => now()->subDays($this->faker->numberBetween(1, 14))->toDateString(),
            'description' => $this->faker->randomElement([
                'Placed gel bait under the sink',
                'Sealed the gap behind the dishwasher',
                'Deep-cleaned behind the fridge',
                'Set sticky traps along the baseboard',
            ]),
        ];
    }

    /**
     * Done in the room with this name at the intervention's own property,
     * created on the way if it is new.
     */
    public function inRoom(string $name): static
    {
        return $this->afterCreating(function (Intervention $intervention) use ($name) {
            $intervention->update(['room_id' => Room::resolve($intervention->user_id, $intervention->customer_id, $name)?->id]);
        });
    }
}
