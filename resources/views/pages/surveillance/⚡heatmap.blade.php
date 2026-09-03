<?php

use App\Actions\Surveillance\ComputeEntryPointHeatmap;
use App\Actions\Surveillance\ComputeNightlyTrend;
use App\Models\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Entry points')] class extends Component {
    /** Empty means every customer, which only makes sense for a single property. */
    #[Url]
    public string $customer = '';

    /** Empty means every room, which only makes sense if the camera never moved. */
    #[Url]
    public string $room = '';

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function heatmap(): array
    {
        return app(ComputeEntryPointHeatmap::class)->handle(
            Auth::user(),
            $this->room !== '' ? $this->room : null,
            $this->customer !== '' ? (int) $this->customer : null,
        );
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function rooms(): array
    {
        return app(ComputeNightlyTrend::class)->rooms(
            Auth::user(),
            $this->customer !== '' ? (int) $this->customer : null,
        );
    }

    /**
     * @return Collection<int, Customer>
     */
    #[Computed]
    public function customers(): Collection
    {
        return Auth::user()->customers()->orderBy('name')->get();
    }

    /**
     * Rooms belong to a property, so switching customer clears a stale room filter.
     */
    public function updatedCustomer(): void
    {
        $this->room = '';
    }

    /**
     * @return array<string, mixed>
     */
    public function heatmapPayload(): array
    {
        $backdrop = $this->heatmap['backdrop'];

        return [
            'frameWidth' => $backdrop?->frame_width,
            'frameHeight' => $backdrop?->frame_height,
            'referenceImageUrl' => $backdrop !== null && $backdrop->reference_image_path !== null
                ? route('surveillance.reference.show', $backdrop)
                : null,
            'entryZones' => $this->heatmap['entry_zones'],
            'exitZones' => $this->heatmap['exit_zones'],
        ];
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Entry points') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Where bugs enter and leave the frame, aggregated across every completed night.') }}</flux:text>
        </div>
        <div class="flex items-center gap-3">
            @if ($this->customers->isNotEmpty())
                <flux:select wire:model.live="customer" size="sm" class="max-w-48" data-test="customer-filter">
                    <flux:select.option value="">{{ __('All customers') }}</flux:select.option>
                    @foreach ($this->customers as $customerOption)
                        <flux:select.option value="{{ $customerOption->id }}">{{ $customerOption->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            @if ($this->rooms !== [])
                <flux:select wire:model.live="room" size="sm" class="max-w-44" data-test="room-filter">
                    <flux:select.option value="">{{ __('All rooms') }}</flux:select.option>
                    @foreach ($this->rooms as $roomOption)
                        <flux:select.option value="{{ $roomOption }}">{{ $roomOption }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            <flux:button href="{{ route('dashboard') }}" icon="arrow-left">{{ __('Dashboard') }}</flux:button>
        </div>
    </div>

    @if ($this->heatmap['session_count'] === 0)
        <flux:callout icon="map-pin" class="mt-6">
            <flux:callout.heading>{{ __('No completed nights yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Complete at least one surveillance session and its entry and exit points will show up here.') }}</flux:callout.text>
        </flux:callout>
    @else
        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Nights aggregated') }}</flux:text>
                <flux:heading size="xl">{{ $this->heatmap['session_count'] }}</flux:heading>
            </div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Bug sightings') }}</flux:text>
                <flux:heading size="xl">{{ $this->heatmap['track_count'] }}</flux:heading>
            </div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Top entry point') }}</flux:text>
                <flux:heading size="xl">
                    {{ isset($this->heatmap['entry_zones'][0]) ? ucfirst($this->heatmap['entry_zones'][0]['edge']).' '.__('edge') : __('None') }}
                </flux:heading>
            </div>
        </div>

        <div class="mt-6" id="heatmap-app">
            <script type="application/json" id="heatmap-data">@json($this->heatmapPayload())</script>

            <div class="overflow-hidden rounded-xl bg-black">
                <canvas data-heatmap="canvas" class="w-full"></canvas>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-6 text-sm text-zinc-500">
                <span class="flex items-center gap-2">
                    <span class="size-3 rounded-full bg-green-400"></span>
                    {{ __('Entry zones (count = sightings entering there)') }}
                </span>
                <span class="flex items-center gap-2">
                    <span class="size-3 rounded-full bg-red-400"></span>
                    {{ __('Exit zones') }}
                </span>
            </div>

            <flux:text class="mt-4 text-sm">
                @if ($this->customers->isNotEmpty() && $this->customer === '')
                    {{ __('Showing every customer at once, which merges unrelated properties onto one backdrop. Pick a customer above.') }}
                @elseif ($this->room !== '')
                    {{ __('Showing nights recorded in :room. The backdrop is that room\'s most recent reference photo; nights shot at a different resolution are scaled to match.', ['room' => $this->room]) }}
                @else
                    {{ __('Showing every room at once, which only makes sense if the camera never moved. Pick a room above to compare nights shot from one spot.') }}
                @endif
            </flux:text>
        </div>

        @vite('resources/js/surveillance/heatmap.js')
    @endif
</section>
