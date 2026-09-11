<?php

namespace App\Concerns;

use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ValidatedInput;
use Illuminate\Validation\Rule;

trait SurveillanceValidationRules
{
    /**
     * Validate the request and hand back the validated payload as a container of
     * typed accessors. The capture page posts deeply nested track data, and
     * reading it back through `integer()`/`string()` keeps the ingestion path
     * from working with untyped values.
     *
     * @param  array<string, array<int, string>>  $rules
     */
    protected function validatedInput(Request $request, array $rules): ValidatedInput
    {
        return Validator::make($request->all(), $rules)->safe();
    }

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
            'planned_end_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * Get the validation rules for a batch of closed tracks.
     *
     * @return array<string, array<int, string>>
     */
    protected function trackBatchRules(SurveillanceSession $session): array
    {
        return [
            'tracks' => ['required', 'array', 'min:1', 'max:50'],
            ...$this->trackRules('max:'.($session->frame_width ?? 4096), 'max:'.($session->frame_height ?? 4096)),
        ];
    }

    /**
     * Get the validation rules for importing a night recorded in the browser
     * without an account. The frame comes with the payload, so the points are
     * bounded by it rather than by a stored session. A night with no sightings
     * is still a night, so the track list may be empty. A customer, if named,
     * has to be one of the importing user's own.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function importNightRules(User $user): array
    {
        return [
            'local_id' => ['required', 'uuid'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after_or_equal:started_at'],
            'aborted' => ['sometimes', 'boolean'],
            'room' => ['nullable', 'string', 'max:80'],
            'customer_id' => ['sometimes', 'nullable', 'integer', Rule::exists('customers', 'id')->where('user_id', $user->id)],
            'frame_width' => ['required', 'integer', 'between:160,4096'],
            'frame_height' => ['required', 'integer', 'between:160,4096'],
            'settings' => ['sometimes', 'array'],
            'reference_image' => ['sometimes', 'nullable', 'string', 'max:2800000'],
            'tracks' => ['present', 'array', 'max:50'],
            'tracks.*.dismissed' => ['sometimes', 'boolean'],
            ...$this->trackRules('lte:frame_width', 'lte:frame_height'),
        ];
    }

    /**
     * The rules for one closed track in the ingest shape, shared by the live
     * upload and the import.
     *
     * @return array<string, array<int, string>>
     */
    private function trackRules(string $xBound, string $yBound): array
    {
        return [
            'tracks.*.client_track_id' => ['required', 'string', 'max:64'],
            'tracks.*.start_offset_ms' => ['required', 'integer', 'min:0'],
            'tracks.*.end_offset_ms' => ['required', 'integer', 'min:0', 'gte:tracks.*.start_offset_ms'],
            'tracks.*.points' => ['required', 'array', 'min:2', 'max:5000'],
            'tracks.*.points.*' => ['required', 'array', 'size:3'],
            'tracks.*.points.*.0' => ['required', 'integer', 'min:0'],
            'tracks.*.points.*.1' => ['required', 'integer', 'min:0', $xBound],
            'tracks.*.points.*.2' => ['required', 'integer', 'min:0', $yBound],
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
