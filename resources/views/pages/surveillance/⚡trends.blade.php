<?php

use App\Actions\Surveillance\ComputeNightlyTrend;
use App\Models\Customer;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Trends')] class extends Component {
    /** Empty means every customer. */
    #[Url]
    public string $customer = '';

    /** Empty means every room. */
    #[Url]
    public string $room = '';

    public string $performedOn = '';

    public string $description = '';

    public function mount(): void
    {
        $this->performedOn = now()->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function trend(): array
    {
        return app(ComputeNightlyTrend::class)->handle(Auth::user(), $this->selectedRoom(), $this->selectedCustomerId());
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function rooms(): array
    {
        return app(ComputeNightlyTrend::class)->rooms(Auth::user(), $this->selectedCustomerId());
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

    private function selectedCustomerId(): ?int
    {
        return $this->customer !== '' ? (int) $this->customer : null;
    }

    private function selectedRoom(): ?string
    {
        return $this->room !== '' ? $this->room : null;
    }

    /**
     * Record something done to fight the infestation. It is attached to the room
     * currently being viewed so its effect shows against that room's nights.
     */
    public function addIntervention(): void
    {
        $validated = $this->validate([
            'performedOn' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        Auth::user()->interventions()->create([
            'customer_id' => $this->selectedCustomerId(),
            'room' => $this->selectedRoom(),
            'performed_on' => $validated['performedOn'],
            'description' => $validated['description'],
        ]);

        $this->reset('description');
        $this->performedOn = now()->toDateString();
        unset($this->trend);

        Flux::toast(variant: 'success', text: __('Intervention recorded.'));
    }

    public function deleteIntervention(int $interventionId): void
    {
        Auth::user()->interventions()->findOrFail($interventionId)->delete();

        unset($this->trend);

        Flux::toast(text: __('Intervention removed.'));
    }

    /**
     * Lay out the column chart: one band per recorded night, bars anchored to the
     * baseline with rounded tops, plus intervention markers positioned in the gap
     * before the first night they could have affected.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function chart(): array
    {
        $nights = $this->trend['nights'];
        $count = count($nights);

        $padLeft = 36.0;
        $padTop = 18.0;
        $plotWidth = 672.0;
        $plotHeight = 204.0;
        $baseline = $padTop + $plotHeight;

        $counts = array_column($nights, 'count');
        $maxCount = max(1, $counts === [] ? 0 : max($counts));
        $bandWidth = $count > 0 ? $plotWidth / $count : $plotWidth;
        $barWidth = max(3.0, min($bandWidth - 6, 46.0));

        $busiestDate = $this->trend['busiest']['date'] ?? null;
        $latestDate = $this->trend['latest']['date'] ?? null;
        $labelStep = max(1, (int) ceil($count / 12));

        $bars = [];

        foreach ($nights as $index => $night) {
            $height = $night['count'] > 0 ? $night['count'] / $maxCount * $plotHeight : 0.0;
            $x = $padLeft + $index * $bandWidth + ($bandWidth - $barWidth) / 2;

            $bars[] = [
                'night' => $night,
                'x' => round($x, 2),
                'width' => round($barWidth, 2),
                'centre' => round($x + $barWidth / 2, 2),
                'top' => round($baseline - $height, 2),
                'path' => $this->barPath($x, $baseline - $height, $barWidth, $height, $baseline),
                'empty' => $night['count'] === 0,
                'labelled' => $night['date'] === $busiestDate || $night['date'] === $latestDate,
                'ticked' => $index % $labelStep === 0 || $index === $count - 1,
            ];
        }

        // Stack badges when several interventions land in the same gap.
        $seenPositions = [];
        $markers = [];

        foreach ($this->trend['interventions'] as $intervention) {
            $position = $intervention['position'];
            $stack = $seenPositions[$position] ?? 0;
            $seenPositions[$position] = $stack + 1;

            $markers[] = [
                'intervention' => $intervention,
                'x' => round(min($padLeft + $position * $bandWidth, $padLeft + $plotWidth), 2),
                'badgeY' => round($padTop + 7 + $stack * 20, 2),
            ];
        }

        $gridValues = array_values(array_unique([0, (int) ceil($maxCount / 2), $maxCount]));

        return [
            'baseline' => $baseline,
            'padLeft' => $padLeft,
            'padTop' => $padTop,
            'plotWidth' => $plotWidth,
            'bars' => $bars,
            'markers' => $markers,
            'grid' => array_map(fn (int $value) => [
                'value' => $value,
                'y' => round($baseline - ($value / $maxCount) * $plotHeight, 2),
            ], $gridValues),
        ];
    }

    /**
     * A bar with rounded top corners, squared off where it meets the baseline.
     */
    private function barPath(float $x, float $y, float $width, float $height, float $baseline): string
    {
        if ($height <= 0.0) {
            return '';
        }

        $radius = min(4.0, $width / 2, $height);
        $right = $x + $width;

        return implode(' ', [
            'M'.round($x, 2).' '.round($baseline, 2),
            'L'.round($x, 2).' '.round($y + $radius, 2),
            'Q'.round($x, 2).' '.round($y, 2).' '.round($x + $radius, 2).' '.round($y, 2),
            'L'.round($right - $radius, 2).' '.round($y, 2),
            'Q'.round($right, 2).' '.round($y, 2).' '.round($right, 2).' '.round($y + $radius, 2),
            'L'.round($right, 2).' '.round($baseline, 2),
            'Z',
        ]);
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Trends') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Sightings per night, and whether what you did about them worked.') }}</flux:text>
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

    @if ($this->trend['nights'] === [])
        <flux:callout icon="chart-bar" class="mt-6">
            <flux:callout.heading>{{ __('No finished nights yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Once a session ends, its sightings show up here as a nightly count.') }}</flux:callout.text>
        </flux:callout>
    @else
        @php
            $latest = $this->trend['latest'];
            $previous = $this->trend['previous'];
            $delta = $previous !== null ? $latest['count'] - $previous['count'] : null;
        @endphp

        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Sightings across :count nights', ['count' => count($this->trend['nights'])]) }}</flux:text>
                <flux:heading size="xl">{{ $this->trend['total_sightings'] }}</flux:heading>
            </div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Busiest night') }}</flux:text>
                <flux:heading size="xl">{{ $this->trend['busiest']['label'] }} &middot; {{ $this->trend['busiest']['count'] }}</flux:heading>
            </div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Last night (:label)', ['label' => $latest['label']]) }}</flux:text>
                <flux:heading size="xl">{{ $latest['count'] }}</flux:heading>
                @if ($delta !== null)
                    <flux:text class="mt-1 text-sm">
                        @if ($delta < 0)
                            <span class="text-green-600 dark:text-green-400">&darr; {{ abs($delta) }} {{ __('fewer') }}</span>
                        @elseif ($delta > 0)
                            <span class="text-red-600 dark:text-red-400">&uarr; {{ $delta }} {{ __('more') }}</span>
                        @else
                            {{ __('No change') }}
                        @endif
                        {{ __('vs :label', ['label' => $previous['label']]) }}
                    </flux:text>
                @endif
            </div>
        </div>

        <div class="mt-6 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <svg
                viewBox="0 0 720 260"
                class="w-full"
                role="img"
                aria-label="{{ __('Confirmed sightings per night') }}"
                data-test="trend-chart"
            >
                @foreach ($this->chart['grid'] as $line)
                    <line
                        x1="{{ $this->chart['padLeft'] }}" y1="{{ $line['y'] }}"
                        x2="{{ $this->chart['padLeft'] + $this->chart['plotWidth'] }}" y2="{{ $line['y'] }}"
                        class="stroke-zinc-200 dark:stroke-zinc-700" stroke-width="1"
                    />
                    <text
                        x="{{ $this->chart['padLeft'] - 8 }}" y="{{ $line['y'] + 4 }}"
                        text-anchor="end" font-size="11"
                        class="fill-zinc-500 dark:fill-zinc-400"
                    >{{ $line['value'] }}</text>
                @endforeach

                @foreach ($this->chart['bars'] as $bar)
                    <g>
                        <title>{{ $bar['night']['label'] }}: {{ $bar['night']['count'] }} {{ trans_choice('sighting|sightings', $bar['night']['count']) }}</title>
                        @if ($bar['empty'])
                            <rect
                                x="{{ $bar['x'] }}" y="{{ $this->chart['baseline'] - 2 }}"
                                width="{{ $bar['width'] }}" height="2"
                                class="fill-zinc-300 dark:fill-zinc-600"
                            />
                        @else
                            <path d="{{ $bar['path'] }}" class="fill-[#2a78d6] dark:fill-[#3987e5]" />
                        @endif

                        @if ($bar['labelled'] && ! $bar['empty'])
                            <text
                                x="{{ $bar['centre'] }}" y="{{ $bar['top'] - 6 }}"
                                text-anchor="middle" font-size="11" font-weight="600"
                                class="fill-zinc-700 dark:fill-zinc-200"
                            >{{ $bar['night']['count'] }}</text>
                        @endif

                        @if ($bar['ticked'])
                            <text
                                x="{{ $bar['centre'] }}" y="{{ $this->chart['baseline'] + 16 }}"
                                text-anchor="middle" font-size="11"
                                class="fill-zinc-500 dark:fill-zinc-400"
                            >{{ $bar['night']['label'] }}</text>
                        @endif
                    </g>
                @endforeach

                <line
                    x1="{{ $this->chart['padLeft'] }}" y1="{{ $this->chart['baseline'] }}"
                    x2="{{ $this->chart['padLeft'] + $this->chart['plotWidth'] }}" y2="{{ $this->chart['baseline'] }}"
                    class="stroke-zinc-300 dark:stroke-zinc-600" stroke-width="1"
                />

                @foreach ($this->chart['markers'] as $marker)
                    <g>
                        <title>{{ $marker['intervention']['label'] }}: {{ $marker['intervention']['description'] }}</title>
                        <line
                            x1="{{ $marker['x'] }}" y1="{{ $this->chart['padTop'] }}"
                            x2="{{ $marker['x'] }}" y2="{{ $this->chart['baseline'] }}"
                            class="stroke-zinc-400 dark:stroke-zinc-500" stroke-width="1.5" stroke-dasharray="4 3"
                        />
                        <circle
                            cx="{{ $marker['x'] }}" cy="{{ $marker['badgeY'] }}" r="8"
                            class="fill-zinc-700 dark:fill-zinc-300"
                        />
                        <text
                            x="{{ $marker['x'] }}" y="{{ $marker['badgeY'] + 4 }}"
                            text-anchor="middle" font-size="10" font-weight="700"
                            class="fill-white dark:fill-zinc-900"
                        >{{ $marker['intervention']['marker'] }}</text>
                    </g>
                @endforeach
            </svg>

            <details class="mt-2">
                <summary class="cursor-pointer text-sm text-zinc-500">{{ __('View as table') }}</summary>
                <flux:table class="mt-3">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Night') }}</flux:table.column>
                        <flux:table.column>{{ __('Sightings') }}</flux:table.column>
                        <flux:table.column>{{ __('Sessions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->trend['nights'] as $night)
                            <flux:table.row wire:key="night-{{ $night['date'] }}">
                                <flux:table.cell variant="strong">{{ $night['label'] }}</flux:table.cell>
                                <flux:table.cell>{{ $night['count'] }}</flux:table.cell>
                                <flux:table.cell>{{ $night['session_count'] }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </details>
        </div>
    @endif

    <flux:heading size="lg" class="mt-8">{{ __('What you did about it') }}</flux:heading>
    @php
        $scope = collect([
            $this->customer !== '' ? $this->customers->firstWhere('id', (int) $this->customer)?->name : null,
            $this->room !== '' ? $this->room : null,
        ])->filter()->implode(' · ');
    @endphp

    <flux:text class="mt-1 text-sm">
        {{ $scope !== ''
            ? __('Recorded against :scope. New ones are filed there too.', ['scope' => $scope])
            : __('Bait, sealed gaps, clean-ups. Each one is numbered on the chart so you can see what happened after it.') }}
    </flux:text>

    <form wire:submit="addIntervention" class="mt-4 flex flex-wrap items-start gap-3">
        <flux:input
            type="date"
            wire:model="performedOn"
            :label="__('Date')"
            class="max-w-44"
            data-test="intervention-date"
        />
        <flux:input
            wire:model="description"
            :label="__('What you did')"
            :placeholder="__('Placed gel bait under the sink')"
            class="min-w-64 flex-1"
            data-test="intervention-description"
        />
        <flux:button type="submit" variant="primary" class="mt-6" data-test="add-intervention-button">
            {{ __('Record') }}
        </flux:button>
    </form>

    @if ($this->trend['interventions'] !== [])
        <div class="mt-4 divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($this->trend['interventions'] as $intervention)
                <div class="flex items-center gap-3 p-3" wire:key="intervention-{{ $intervention['id'] }}">
                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-zinc-700 text-xs font-bold text-white dark:bg-zinc-300 dark:text-zinc-900">
                        {{ $intervention['marker'] }}
                    </span>
                    <div class="flex-1">
                        <flux:text class="font-medium">{{ $intervention['description'] }}</flux:text>
                        <flux:text class="text-sm">{{ $intervention['label'] }}</flux:text>
                    </div>
                    <flux:button
                        size="sm"
                        variant="subtle"
                        icon="trash"
                        wire:click="deleteIntervention({{ $intervention['id'] }})"
                        wire:confirm="{{ __('Remove this intervention?') }}"
                        data-test="delete-intervention-button"
                    />
                </div>
            @endforeach
        </div>
    @endif
</section>
