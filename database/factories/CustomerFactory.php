<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
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
            'name' => $this->faker->unique()->company(),
            'address' => $this->faker->streetAddress(),
            'notes' => null,
        ];
    }

    /**
     * A property whose owner has accepted an invitation to the client portal.
     */
    public function linkedTo(User $client): static
    {
        return $this->state(fn (array $attributes) => [
            'client_user_id' => $client->id,
        ]);
    }

    /**
     * A property with an open invitation link to the client portal.
     */
    public function invited(): static
    {
        return $this->state(fn (array $attributes) => [
            'portal_invite_token' => Str::random(48),
            'portal_invited_at' => now(),
        ]);
    }
}
