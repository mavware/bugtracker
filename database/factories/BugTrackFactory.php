<?php

namespace Database\Factories;

use App\Models\BugTrack;
use App\Models\SurveillanceSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BugTrack>
 */
class BugTrackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startOffsetMs = $this->faker->numberBetween(0, 6 * 60 * 60 * 1000);
        $points = $this->randomWalkPoints($startOffsetMs);
        $lastPoint = end($points);

        return [
            'surveillance_session_id' => SurveillanceSession::factory(),
            'client_track_id' => $this->faker->uuid(),
            'start_offset_ms' => $startOffsetMs,
            'end_offset_ms' => $lastPoint[0],
            'point_count' => count($points),
            'points' => $points,
            'entry_edge' => $this->faker->randomElement(['top', 'bottom', 'left', 'right', 'interior']),
            'exit_edge' => $this->faker->randomElement(['top', 'bottom', 'left', 'right', 'interior']),
        ];
    }

    /**
     * Mark the track as dismissed as a false positive.
     */
    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'dismissed_at' => now(),
        ]);
    }

    /**
     * Generate a plausible random-walk of [t_offset_ms, x, y] triplets within a 1280x720 frame.
     *
     * @return non-empty-array<int, array{0: int, 1: int, 2: int}>
     */
    private function randomWalkPoints(int $startOffsetMs): array
    {
        $t = $startOffsetMs;
        $x = $this->faker->numberBetween(0, 1280);
        $y = $this->faker->numberBetween(0, 720);

        $points = [[$t, $x, $y]];

        $count = $this->faker->numberBetween(30, 200);

        for ($i = 1; $i < $count; $i++) {
            $t += $this->faker->numberBetween(120, 250);
            $x = max(0, min(1280, $x + $this->faker->numberBetween(-25, 25)));
            $y = max(0, min(720, $y + $this->faker->numberBetween(-25, 25)));
            $points[] = [$t, $x, $y];
        }

        return $points;
    }
}
