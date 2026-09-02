<?php

namespace App\Models;

use Database\Factories\BugTrackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $surveillance_session_id
 * @property string $client_track_id
 * @property int $start_offset_ms
 * @property int $end_offset_ms
 * @property int $point_count
 * @property array<int, array{0: int, 1: int, 2: int}> $points
 * @property string|null $entry_edge
 * @property string|null $exit_edge
 * @property string|null $start_crop_path
 * @property string|null $end_crop_path
 * @property Carbon|null $dismissed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['client_track_id', 'start_offset_ms', 'end_offset_ms', 'point_count', 'points', 'entry_edge', 'exit_edge', 'start_crop_path', 'end_crop_path', 'dismissed_at'])]
class BugTrack extends Model
{
    /** @use HasFactory<BugTrackFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points' => 'array',
            'dismissed_at' => 'datetime',
        ];
    }

    /**
     * Scope a query to tracks not dismissed as false positives.
     *
     * @param  Builder<BugTrack>  $query
     */
    #[Scope]
    protected function confirmed(Builder $query): void
    {
        $query->whereNull('dismissed_at');
    }

    /**
     * @return BelongsTo<SurveillanceSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SurveillanceSession::class, 'surveillance_session_id');
    }
}
