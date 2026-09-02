<?php

namespace Database\Factories;

use App\Enums\SurveillanceSessionStatus;
use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveillanceSession>
 */
class SurveillanceSessionFactory extends Factory
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
            'name' => 'Night of '.$this->faker->date('M j'),
            'status' => SurveillanceSessionStatus::Pending,
        ];
    }

    /**
     * A session that is actively recording.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SurveillanceSessionStatus::Active,
            'started_at' => now()->subHours(2),
            'last_heartbeat_at' => now(),
            'reference_image_path' => 'surveillance/1/reference.jpg',
            'frame_width' => 1280,
            'frame_height' => 720,
        ]);
    }

    /**
     * A finished session with a full night recorded.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SurveillanceSessionStatus::Completed,
            'started_at' => now()->subHours(9),
            'ended_at' => now()->subHour(),
            'last_heartbeat_at' => now()->subHour(),
            'reference_image_path' => 'surveillance/1/reference.jpg',
            'frame_width' => 1280,
            'frame_height' => 720,
        ]);
    }
}
