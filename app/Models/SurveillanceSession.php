<?php

namespace App\Models;

use App\Enums\SurveillanceSessionStatus;
use Database\Factories\SurveillanceSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $room
 * @property SurveillanceSessionStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $last_heartbeat_at
 * @property string|null $reference_image_path
 * @property int|null $frame_width
 * @property int|null $frame_height
 * @property array<string, mixed>|null $settings
 * @property array<string, mixed>|null $analytics
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'room', 'status', 'started_at', 'ended_at', 'last_heartbeat_at', 'reference_image_path', 'frame_width', 'frame_height', 'settings', 'analytics'])]
class SurveillanceSession extends Model
{
    /** @use HasFactory<SurveillanceSessionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SurveillanceSessionStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'settings' => 'array',
            'analytics' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (SurveillanceSession $session) {
            Storage::disk('local')->deleteDirectory($session->storageDirectory());
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<BugTrack, $this>
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(BugTrack::class);
    }

    public function storageDirectory(): string
    {
        return "surveillance/{$this->id}";
    }
}
