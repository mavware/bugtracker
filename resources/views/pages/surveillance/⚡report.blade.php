<?php

use App\Actions\Surveillance\ComputeSessionAnalytics;
use App\Actions\Surveillance\DescribeNightInProgress;
use App\Enums\SurveillanceSessionStatus;
use App\Models\SurveillanceSession;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Report')]
class extends Component {
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
     * How the night is going while the camera is still on it. Null once it is over.
     *
     * @return array{sightings: int, last_sighting_at: Carbon|null, heartbeat_stale: bool, overdue: bool}|null
     */
    #[Computed]
    public function nightInProgress(): ?array
    {
        if ($this->session->status !== SurveillanceSessionStatus::Active) {
            return null;
        }

        return app(DescribeNightInProgress::class)->handle($this->session);
    }

    /**
     * Polled while the night runs. Once the device has ended it, reload so the
     * report and its replay script start from a full page rather than a morph.
     */
    public function refreshNightInProgress(): void
    {
        if ($this->session->status->isFinished()) {
            $this->redirectRoute('surveillance.report', $this->session);
        }
    }

    /**
     * Close a night whose device has gone quiet. The device owns the night while
     * it is checking in — ending it from here then would 409 its uploads and lose
     * the tracks still on it — so this is refused until the heartbeat is stale.
     * The night ends at the last check-in, the last moment anything was watching.
     */
    public function endStuckNight(ComputeSessionAnalytics $analytics): void
    {
        Gate::authorize('update', $this->session);

        if ($this->session->status !== SurveillanceSessionStatus::Active) {
            return;
        }

        if (! $this->nightInProgress['heartbeat_stale']) {
            Flux::toast(variant: 'warning', text: __('The capture device is still checking in. End the night from that device.'));

            return;
        }

        $this->session->update([
            'status' => SurveillanceSessionStatus::Completed,
            'ended_at' => $this->session->last_heartbeat_at ?? $this->session->started_at ?? now(),
        ]);

        $analytics->handle($this->session);

        $this->redirectRoute('surveillance.report', $this->session);
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
     * Leave this night out of trends and entry points, or put it back in. The
     * choice belongs here rather than at the camera: whether the setup was any
     * good is something only the finished report shows.
     */
    public function toggleDiscarded(): void
    {
        Gate::authorize('update', $this->session);

        $this->session->update([
            'status' => $this->session->status === SurveillanceSessionStatus::Aborted
                ? SurveillanceSessionStatus::Completed
                : SurveillanceSessionStatus::Aborted,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function reportPayload(): array
    {
        return [
            'frameWidth'        => $this->session->frame_width,
            'frameHeight'       => $this->session->frame_height,
            'referenceImageUrl' => $this->session->reference_image_path !== null
                ? route('surveillance.reference.show', $this->session)
                : null,
            'analytics'         => $this->session->analytics,
            'tracks'            => $this->session->tracks()
                ->confirmed()
                ->orderBy('start_offset_ms')
                ->get()
                ->map(fn($track) => [
                    'id'            => $track->id,
                    'startOffsetMs' => $track->start_offset_ms,
                    'endOffsetMs'   => $track->end_offset_ms,
                    'points'        => $track->points,
                    'entryEdge'     => $track->entry_edge,
                    'exitEdge'      => $track->exit_edge,
                ])->all(),
        ];
    }
}; ?>

<section class="w-full">
    @if ($session->status === SurveillanceSessionStatus::Pending)
        <flux:heading size="xl">{{ $session->name }}</flux:heading>
        <flux:callout icon="video-camera" class="mt-6" data-test="night-not-started">
            <flux:callout.heading>{{ __('This night has not started yet') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Set the camera up on the device that will watch the room.') }}
                <flux:link href="{{ route('surveillance.capture', $session) }}">{{ __('Go to the capture page') }}</flux:link>
            </flux:callout.text>
        </flux:callout>
    @elseif ($session->status === SurveillanceSessionStatus::Active)
        @php($night = $this->nightInProgress)

        <div wire:poll.30s="refreshNightInProgress" data-test="night-in-progress">
            <div class="flex items-center gap-2">
                <span class="relative flex size-2.5">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex size-2.5 rounded-full bg-green-500"></span>
                </span>
                <flux:text class="text-sm font-medium">{{ __('Recording now') }}</flux:text>
            </div>
            <flux:heading size="xl" class="mt-2">{{ $session->name }}</flux:heading>
            <flux:text class="mt-2">
                {{ __('Started :time', ['time' => $session->started_at?->format('H:i') ?? '—']) }}
                @if ($session->planned_end_at !== null)
                    <span class="text-zinc-400">&middot;</span>
                    {{ __('ends :time', ['time' => $session->planned_end_at->format('H:i')]) }}
                @endif
                <span class="text-zinc-400">&middot;</span>
                {{ __('The report is written once the night ends. This page checks every 30 seconds.') }}
            </flux:text>

            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text class="text-sm">{{ __('Sightings so far') }}</flux:text>
                    <flux:heading size="xl" data-test="night-sightings">{{ $night['sightings'] }}</flux:heading>
                </div>
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text class="text-sm">{{ __('Last seen') }}</flux:text>
                    <flux:heading size="xl">{{ $night['last_sighting_at']?->format('H:i') ?? '—' }}</flux:heading>
                </div>
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text class="text-sm">{{ __('Last check-in') }}</flux:text>
                    <flux:heading size="xl">{{ $session->last_heartbeat_at?->format('H:i') ?? '—' }}</flux:heading>
                </div>
            </div>

            {{-- Only a quiet device can be ended from here; a live one is ending itself on its next tick. --}}
            @if ($night['heartbeat_stale'])
                <flux:callout variant="warning" icon="{{ $night['overdue'] ? 'clock' : 'exclamation-triangle' }}" class="mt-6" data-test="night-stuck">
                    <flux:callout.heading>
                        @if ($night['overdue'])
                            {{ __('The night was due to end at :time', ['time' => $session->planned_end_at->format('H:i')]) }}
                        @else
                            {{ __('The capture device has gone quiet') }}
                        @endif
                    </flux:callout.heading>
                    <flux:callout.text>
                        {{ __('No check-in since :time, so the screen probably slept or the tab was closed and sightings are no longer being recorded. If that device is still to hand, press End night there. Otherwise end the night here: it closes at the last check-in and the report is written from everything it sent before then.', [
                            'time' => $session->last_heartbeat_at?->diffForHumans() ?? __('the session started'),
                        ]) }}
                    </flux:callout.text>
                    <x-slot name="actions">
                        <flux:button
                            variant="primary"
                            icon="stop-circle"
                            wire:click="endStuckNight"
                            wire:confirm="{{ __('End this night now? Anything still on the capture device will not make it into the report.') }}"
                            data-test="end-night-button"
                        >{{ __('End night now') }}</flux:button>
                    </x-slot>
                </flux:callout>
            @endif
        </div>
    @else
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ $session->name }}</flux:heading>
                <flux:text class="mt-2">
                    {{ $session->started_at?->format('M j, H:i') }} – {{ $session->ended_at?->format('M j, H:i') }}
                </flux:text>
            </div>
            <div class="flex items-center gap-2">
                <flux:button
                    variant="{{ $session->status === SurveillanceSessionStatus::Aborted ? 'filled' : 'subtle' }}"
                    icon="{{ $session->status === SurveillanceSessionStatus::Aborted ? 'arrow-uturn-left' : 'x-circle' }}"
                    wire:click="toggleDiscarded"
                    data-test="toggle-discarded-button"
                >
                    {{ $session->status === SurveillanceSessionStatus::Aborted ? __('Keep this night') : __('Discard night') }}
                </flux:button>
            </div>
        </div>

        @if ($session->status === SurveillanceSessionStatus::Aborted)
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

            <x-surveillance.replay-controls />

            @if ($session->tracks->isNotEmpty())
                <flux:heading size="lg" class="mt-8">{{ __('Sightings') }}</flux:heading>
                <flux:text
                    class="mt-1 text-sm">{{ __('Snapshots let you verify each sighting was really a bug. Click a row to highlight its trail, or mark false positives to exclude them from the report.') }}</flux:text>

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
                                <flux:table.cell>{{ round(($track->end_offset_ms - $track->start_offset_ms) / 1000, 1) }}
                                    s
                                </flux:table.cell>
                                <flux:table.cell>{{ ucfirst($track->entry_edge ?? '—') }}</flux:table.cell>
                                <flux:table.cell>{{ ucfirst($track->exit_edge ?? '—') }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($track->start_crop_path !== null)
                                        <img src="{{ route('surveillance.crop.show', [$session, $track, 'start']) }}"
                                             alt="" class="size-12 rounded object-cover" loading="lazy"/>
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($track->end_crop_path !== null)
                                        <img src="{{ route('surveillance.crop.show', [$session, $track, 'end']) }}"
                                             alt="" class="size-12 rounded object-cover" loading="lazy"/>
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
