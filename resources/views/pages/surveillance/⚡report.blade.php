<?php

use App\Actions\Surveillance\ComputeSessionAnalytics;
use App\Models\SurveillanceSession;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Report')] class extends Component {
    public SurveillanceSession $session;

    public function mount(SurveillanceSession $session, ComputeSessionAnalytics $analytics): void
    {
        Gate::authorize('view', $session);

        $this->session = $session;

        if ($session->status->isFinished() && $session->analytics === null) {
            $analytics->handle($session);
        }
    }

    /**
     * Dismiss a track as a false positive, or restore it, and refresh the analytics.
     */
    public function toggleDismissed(ComputeSessionAnalytics $analytics, int $trackId): void
    {
        Gate::authorize('update', $this->session);

        $track = $this->session->tracks()->findOrFail($trackId);
        $track->update(['dismissed_at' => $track->dismissed_at === null ? now() : null]);

        $analytics->handle($this->session);

        $this->dispatch('surveillance-report-updated');
    }

    /**
     * @return array<string, mixed>
     */
    public function reportPayload(): array
    {
        return [
            'frameWidth' => $this->session->frame_width,
            'frameHeight' => $this->session->frame_height,
            'referenceImageUrl' => $this->session->reference_image_path !== null
                ? route('surveillance.reference.show', $this->session)
                : null,
            'analytics' => $this->session->analytics,
            'tracks' => $this->session->tracks()
                ->confirmed()
                ->orderBy('start_offset_ms')
                ->get()
                ->map(fn ($track) => [
                    'id' => $track->id,
                    'startOffsetMs' => $track->start_offset_ms,
                    'endOffsetMs' => $track->end_offset_ms,
                    'points' => $track->points,
                    'entryEdge' => $track->entry_edge,
                    'exitEdge' => $track->exit_edge,
                ])->all(),
        ];
    }
}; ?>

<section class="w-full">
    @if (! $session->status->isFinished())
        <flux:heading size="xl">{{ $session->name }}</flux:heading>
        <flux:callout icon="video-camera" class="mt-6">
            <flux:callout.heading>{{ __('This session is still recording') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('The report is generated once the night ends.') }}
                <flux:link href="{{ route('surveillance.capture', $session) }}">{{ __('Go to the capture page') }}</flux:link>
            </flux:callout.text>
        </flux:callout>
    @else
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ $session->name }}</flux:heading>
                <flux:text class="mt-2">
                    {{ $session->started_at?->format('M j, H:i') }} – {{ $session->ended_at?->format('M j, H:i') }}
                </flux:text>
            </div>
            <flux:button href="{{ route('dashboard') }}" icon="arrow-left">{{ __('Dashboard') }}</flux:button>
        </div>

        @if ($session->status === \App\Enums\SurveillanceSessionStatus::Aborted)
            <flux:callout icon="x-circle" class="mt-6" data-test="discarded-notice">
                <flux:callout.heading>{{ __('You discarded this night') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('Everything it caught is still here, but it is left out of trends and entry points so a bad setup does not skew them.') }}
                </flux:callout.text>
            </flux:callout>
        @endif

        @php($analytics = $session->analytics ?? [])

        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Bug sightings') }}</flux:text>
                <flux:heading size="xl">{{ $analytics['track_count'] ?? 0 }}</flux:heading>
            </div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Top entry point') }}</flux:text>
                <flux:heading size="xl">
                    {{ isset($analytics['entry_zones'][0]) ? ucfirst($analytics['entry_zones'][0]['edge']).' '.__('edge') : __('None') }}
                </flux:heading>
            </div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Top exit point') }}</flux:text>
                <flux:heading size="xl">
                    {{ isset($analytics['exit_zones'][0]) ? ucfirst($analytics['exit_zones'][0]['edge']).' '.__('edge') : __('None') }}
                </flux:heading>
            </div>
        </div>

        <div class="mt-6" id="report-app">
            <script type="application/json" id="report-data">@json($this->reportPayload())</script>

            <div class="overflow-hidden rounded-xl bg-black">
                <canvas data-report="canvas" class="w-full"></canvas>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <flux:button size="sm" icon="play" data-report="play">{{ __('Replay') }}</flux:button>
                <flux:select size="sm" data-report="speed" class="max-w-32">
                    <flux:select.option value="60">60×</flux:select.option>
                    <flux:select.option value="1">1×</flux:select.option>
                    <flux:select.option value="10">10×</flux:select.option>
                    <flux:select.option value="600">600×</flux:select.option>
                </flux:select>
                <input type="range" data-report="scrub" min="0" max="1000" value="0" class="min-w-48 flex-1" />
                <span class="text-sm tabular-nums text-zinc-500" data-report="clock">–</span>
                <label class="flex items-center gap-2 text-sm text-zinc-500">
                    <input type="checkbox" data-report="trails" class="rounded" checked />
                    {{ __('Show all trails') }}
                </label>
            </div>

            @if ($session->tracks->isNotEmpty())
                <flux:heading size="lg" class="mt-8">{{ __('Sightings') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('Snapshots let you verify each sighting was really a bug. Click a row to highlight its trail, or mark false positives to exclude them from the report.') }}</flux:text>

                <flux:table class="mt-4">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Time') }}</flux:table.column>
                        <flux:table.column>{{ __('Duration') }}</flux:table.column>
                        <flux:table.column>{{ __('Entered') }}</flux:table.column>
                        <flux:table.column>{{ __('Exited') }}</flux:table.column>
                        <flux:table.column>{{ __('First seen') }}</flux:table.column>
                        <flux:table.column>{{ __('Last seen') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($session->tracks->sortBy('start_offset_ms') as $track)
                            <flux:table.row
                                wire:key="track-{{ $track->id }}"
                                data-track-id="{{ $track->id }}"
                                class="cursor-pointer {{ $track->dismissed_at !== null ? 'opacity-40' : '' }}"
                            >
                                <flux:table.cell variant="strong">
                                    {{ $session->started_at?->addMilliseconds($track->start_offset_ms)->format('H:i:s') }}
                                </flux:table.cell>
                                <flux:table.cell>{{ round(($track->end_offset_ms - $track->start_offset_ms) / 1000, 1) }}s</flux:table.cell>
                                <flux:table.cell>{{ ucfirst($track->entry_edge ?? '—') }}</flux:table.cell>
                                <flux:table.cell>{{ ucfirst($track->exit_edge ?? '—') }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($track->start_crop_path !== null)
                                        <img src="{{ route('surveillance.crop.show', [$session, $track, 'start']) }}" alt="" class="size-12 rounded object-cover" loading="lazy" />
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($track->end_crop_path !== null)
                                        <img src="{{ route('surveillance.crop.show', [$session, $track, 'end']) }}" alt="" class="size-12 rounded object-cover" loading="lazy" />
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button
                                        size="sm"
                                        variant="{{ $track->dismissed_at !== null ? 'filled' : 'subtle' }}"
                                        wire:click.stop="toggleDismissed({{ $track->id }})"
                                        data-test="toggle-dismissed-button"
                                    >
                                        {{ $track->dismissed_at !== null ? __('Restore') : __('Not a bug') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @else
                <flux:callout class="mt-8">
                    <flux:callout.heading>{{ __('No bugs detected') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Nothing moved through the frame this night — or the room was too dark to see it.') }}</flux:callout.text>
                </flux:callout>
            @endif
        </div>

        @vite('resources/js/surveillance/report.js')
    @endif
</section>
