<?php

use App\Actions\Surveillance\DescribeNightInProgress;
use App\Enums\SurveillanceSessionStatus;
use App\Models\SurveillanceSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
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
     * @return array{sightings: int, last_sighting_at: Carbon|null, heartbeat_stale: bool, overdue: bool}|null
     */
    #[Computed]
    public function tonight(): ?array
    {
        $session = $this->session;

        if ($session === null) {
            return null;
        }

        return app(DescribeNightInProgress::class)->handle($session);
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
                        @if ($this->session->planned_end_at !== null)
                            <span class="text-zinc-400">&middot;</span>
                            <span data-test="tonight-planned-end">{{ __('ends :time', ['time' => $this->session->planned_end_at->format('H:i')]) }}</span>
                        @endif
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
                    <flux:button size="sm" href="{{ route('surveillance.report', $this->session) }}" data-test="tonight-open-night">
                        {{ __('See how it\'s going') }}
                    </flux:button>
                </div>
            </div>

            @if ($tonight['overdue'])
                <flux:callout variant="warning" icon="clock" class="mt-4" data-test="tonight-overdue">
                    <flux:callout.heading>{{ __('The night was due to end at :time', ['time' => $this->session->planned_end_at->format('H:i')]) }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('The capture device has not ended it, so its screen probably slept before then. Open the capture page on that device and press End night to get the report, or end it from the night\'s page.') }}
                    </flux:callout.text>
                </flux:callout>
            @elseif ($tonight['heartbeat_stale'])
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
