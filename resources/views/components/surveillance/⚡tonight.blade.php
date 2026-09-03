<?php

use App\Enums\SurveillanceSessionStatus;
use App\Models\SurveillanceSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /**
     * The capture device heartbeats every 60s; past this it has probably slept.
     */
    private const STALE_HEARTBEAT_MINUTES = 3;

    #[Computed]
    public function session(): ?SurveillanceSession
    {
        return Auth::user()->surveillanceSessions()
            ->with('customer')
            ->where('status', SurveillanceSessionStatus::Active)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * A read-only glance at the night in progress, for checking from another device.
     *
     * @return array{sightings: int, last_sighting_at: Carbon|null, heartbeat_stale: bool}|null
     */
    #[Computed]
    public function tonight(): ?array
    {
        $session = $this->session;

        if ($session === null) {
            return null;
        }

        $lastOffsetMs = $session->tracks()->confirmed()->max('end_offset_ms');

        return [
            'sightings' => $session->tracks()->confirmed()->count(),
            'last_sighting_at' => $lastOffsetMs !== null && $session->started_at !== null
                ? $session->started_at->copy()->addMilliseconds((int) $lastOffsetMs)
                : null,
            'heartbeat_stale' => $session->last_heartbeat_at === null
                || $session->last_heartbeat_at->lt(now()->subMinutes(self::STALE_HEARTBEAT_MINUTES)),
        ];
    }
}; ?>

<div @if ($this->session !== null) wire:poll.30s @else wire:poll.60s @endif>
    @if ($this->session !== null)
        @php($tonight = $this->tonight)

        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-test="tonight-panel">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="relative flex size-2.5">
                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex size-2.5 rounded-full bg-green-500"></span>
                        </span>
                        <flux:text class="text-sm font-medium">{{ __('Recording now') }}</flux:text>
                    </div>
                    <flux:heading size="lg" class="mt-2">
                        @if ($this->session->customer !== null)
                            {{ $this->session->customer->name }} <span class="text-zinc-400">&middot; {{ $this->session->name }}</span>
                        @else
                            {{ $this->session->name }}
                        @endif
                        @if ($this->session->room) <span class="text-zinc-400">&middot; {{ $this->session->room }}</span>@endif
                    </flux:heading>
                    <flux:text class="mt-1 text-sm">
                        {{ __('Started :time', ['time' => $this->session->started_at?->format('H:i') ?? '—']) }}
                    </flux:text>
                </div>

                <div class="flex items-center gap-8">
                    <div>
                        <flux:text class="text-sm">{{ __('Sightings so far') }}</flux:text>
                        <flux:heading size="xl" data-test="tonight-sightings">{{ $tonight['sightings'] }}</flux:heading>
                    </div>
                    <div>
                        <flux:text class="text-sm">{{ __('Last seen') }}</flux:text>
                        <flux:heading size="xl">{{ $tonight['last_sighting_at']?->format('H:i') ?? '—' }}</flux:heading>
                    </div>
                    <flux:button size="sm" href="{{ route('surveillance.capture', $this->session) }}">
                        {{ __('Open capture') }}
                    </flux:button>
                </div>
            </div>

            @if ($tonight['heartbeat_stale'])
                <flux:callout variant="warning" icon="exclamation-triangle" class="mt-4">
                    <flux:callout.heading>{{ __('The capture device has gone quiet') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('No check-in since :time. The screen may have slept or the tab was closed — sightings are not being recorded.', [
                            'time' => $this->session->last_heartbeat_at?->diffForHumans() ?? __('the session started'),
                        ]) }}
                    </flux:callout.text>
                </flux:callout>
            @endif
        </div>
    @endif
</div>
