<?php

use App\Actions\Surveillance\ComputeEntryPointHeatmap;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Entry points')] class extends Component {
    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function heatmap(): array
    {
        return app(ComputeEntryPointHeatmap::class)->handle(Auth::user());
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
        <flux:button href="{{ route('dashboard') }}" icon="arrow-left">{{ __('Dashboard') }}</flux:button>
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
                {{ __('The backdrop is the most recent completed night\'s reference photo. Older nights recorded at a different resolution are scaled to match, so keep the camera in the same spot for the clearest picture.') }}
            </flux:text>
        </div>

        @vite('resources/js/surveillance/heatmap.js')
    @endif
</section>
