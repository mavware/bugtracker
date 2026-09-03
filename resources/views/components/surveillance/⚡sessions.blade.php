<?php

use App\Models\SurveillanceSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /**
     * Which room the camera is watching. Grouping nights by room keeps the entry
     * point map honest — it only makes sense to merge nights shot from one spot.
     */
    public string $room = '';

    public function mount(): void
    {
        $this->room = (string) Auth::user()->surveillanceSessions()
            ->whereNotNull('room')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('room');
    }

    /**
     * @return Collection<int, SurveillanceSession>
     */
    #[Computed]
    public function sessions(): Collection
    {
        return Auth::user()->surveillanceSessions()
            ->withCount('tracks')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Create a pending session and send the user to the capture page.
     */
    public function startSession(): void
    {
        $validated = $this->validate(['room' => ['nullable', 'string', 'max:80']]);

        $session = Auth::user()->surveillanceSessions()->create([
            'name' => __('Night of :date', ['date' => now()->format('M j')]),
            'room' => trim($validated['room']) !== '' ? trim($validated['room']) : null,
        ]);

        $this->redirectRoute('surveillance.capture', $session);
    }

    /**
     * Delete a session along with its stored images.
     */
    public function deleteSession(int $sessionId): void
    {
        $session = SurveillanceSession::findOrFail($sessionId);

        Gate::authorize('delete', $session);

        $session->delete();

        unset($this->sessions);
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="lg">{{ __('Surveillance') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Overnight bug watching sessions') }}</flux:text>
        </div>

        <div class="flex items-center gap-3">
            <flux:button icon="chart-bar" href="{{ route('surveillance.trends') }}" data-test="trends-link">
                {{ __('Trends') }}
            </flux:button>
            <flux:button icon="map-pin" href="{{ route('surveillance.heatmap') }}" data-test="heatmap-link">
                {{ __('Entry points') }}
            </flux:button>
        </div>
    </div>

    <form wire:submit="startSession" class="mt-4 flex flex-wrap items-end gap-3">
        <flux:input
            wire:model="room"
            :label="__('Room')"
            :placeholder="__('Kitchen')"
            class="max-w-52"
            data-test="session-room"
        />
        <flux:button type="submit" variant="primary" icon="video-camera" data-test="start-session-button">
            {{ __('Start a session') }}
        </flux:button>
    </form>

    <flux:separator class="my-6" />

    @if ($this->sessions->isEmpty())
        <flux:text>{{ __('No sessions yet. Point a camera at the room and start one.') }}</flux:text>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Session') }}</flux:table.column>
                <flux:table.column>{{ __('Room') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Tracks') }}</flux:table.column>
                <flux:table.column>{{ __('Started') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->sessions as $session)
                    <flux:table.row wire:key="session-{{ $session->id }}">
                        <flux:table.cell variant="strong">
                            @if ($session->status->isFinished())
                                <flux:link href="{{ route('surveillance.report', $session) }}">{{ $session->name }}</flux:link>
                            @else
                                <flux:link href="{{ route('surveillance.capture', $session) }}">{{ $session->name }}</flux:link>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $session->room ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($session->status) {
                                \App\Enums\SurveillanceSessionStatus::Pending => 'zinc',
                                \App\Enums\SurveillanceSessionStatus::Active => 'green',
                                \App\Enums\SurveillanceSessionStatus::Completed => 'blue',
                                \App\Enums\SurveillanceSessionStatus::Aborted => 'red',
                            }">{{ ucfirst($session->status->value) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $session->tracks_count }}</flux:table.cell>
                        <flux:table.cell>{{ $session->started_at?->diffForHumans() ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                size="sm"
                                variant="danger"
                                icon="trash"
                                wire:click="deleteSession({{ $session->id }})"
                                wire:confirm="{{ __('Delete this session and all of its data?') }}"
                                data-test="delete-session-button"
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
