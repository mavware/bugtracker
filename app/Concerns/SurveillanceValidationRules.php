<?php

namespace App\Concerns;

use App\Models\SurveillanceSession;

trait SurveillanceValidationRules
{
    /**
     * Get the validation rules for storing a session's reference frame.
     *
     * @return array<string, array<int, string>>
     */
    protected function referenceRules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpeg', 'max:2048'],
            'frame_width' => ['required', 'integer', 'between:160,4096'],
            'frame_height' => ['required', 'integer', 'between:160,4096'],
            'settings' => ['sometimes', 'array'],
        ];
    }

    /**
     * Get the validation rules for a batch of closed tracks.
     *
     * @return array<string, array<int, string>>
     */
    protected function trackBatchRules(SurveillanceSession $session): array
    {
        $maxX = $session->frame_width ?? 4096;
        $maxY = $session->frame_height ?? 4096;

        return [
            'tracks' => ['required', 'array', 'min:1', 'max:50'],
            'tracks.*.client_track_id' => ['required', 'string', 'max:64'],
            'tracks.*.start_offset_ms' => ['required', 'integer', 'min:0'],
            'tracks.*.end_offset_ms' => ['required', 'integer', 'min:0', 'gte:tracks.*.start_offset_ms'],
            'tracks.*.points' => ['required', 'array', 'min:2', 'max:5000'],
            'tracks.*.points.*' => ['required', 'array', 'size:3'],
            'tracks.*.points.*.0' => ['required', 'integer', 'min:0'],
            'tracks.*.points.*.1' => ['required', 'integer', 'min:0', 'max:'.$maxX],
            'tracks.*.points.*.2' => ['required', 'integer', 'min:0', 'max:'.$maxY],
            'tracks.*.start_crop' => ['nullable', 'string', 'max:40000'],
            'tracks.*.end_crop' => ['nullable', 'string', 'max:40000'],
        ];
    }

    /**
     * Get the validation rules for ending a session.
     *
     * @return array<string, array<int, string>>
     */
    protected function endRules(): array
    {
        return [
            'ended_at_offset_ms' => ['required', 'integer', 'min:0'],
            'aborted' => ['sometimes', 'boolean'],
        ];
    }
}
