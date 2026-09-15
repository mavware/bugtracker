<?php

use App\Enums\SurveillanceSessionStatus;
use App\Models\BugTrack;
use App\Models\Customer;
use App\Models\Room;
use App\Models\SurveillanceSession;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The client's side of a professional's customer record: every night recorded
 * at a property linked to this account, with its name and room open to edit.
 * Authorisation is by construction — the page only ever queries nights filed
 * under its own linked properties, so any other night's id is simply not found.
 */
new #[Title('Your properties'), Layout('layouts::app', [
    'heading' => 'Your properties',
    'subHeading' => 'The nights recorded at your home by the professional watching it. Name each night and the room it was shot in so the reports read well.',
])] class extends Component {
    public ?int $editingId = null;

    public string $sessionName = '';

    public string $sessionRoom = '';

    /**
     * @return Collection<int, Customer>
     */
    #[Computed]
    public function properties(): Collection
    {
        return Auth::user()->clientProperties()
            ->with(['user:id,name', 'surveillanceSessions' => fn ($query) => $query
                ->with('room')
                ->withCount(['tracks as confirmed_tracks_count' => fn (Builder $tracks) => $tracks->confirmed()])
                ->orderByRaw('COALESCE(started_at, created_at) DESC')
                ->orderByDesc('id')])
            ->orderBy('name')
            ->get();
    }

    public function startEdit(int $sessionId): void
    {
        $session = $this->session($sessionId);

        $this->editingId = $session->id;
        $this->sessionName = $session->name;
        $this->sessionRoom = (string) $session->room?->name;
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'sessionName', 'sessionRoom');
        $this->resetValidation();
    }

    public function saveSession(): void
    {
        $session = $this->session((int) $this->editingId);

        $validated = $this->validate([
            'sessionName' => ['required', 'string', 'max:255'],
            'sessionRoom' => ['nullable', 'string', 'max:'.Room::NAME_MAX_LENGTH],
        ]);

        $session->update(['name' => trim($validated['sessionName'])]);

        // The room is filed under the property the professional recorded the
        // night at, which is the same room their own rooms page lists.
        $session->moveToRoomNamed($validated['sessionRoom']);

        $this->cancelEdit();
        unset($this->properties);

        Flux::toast(variant: 'success', text: __('Night saved.'));
    }

    /**
     * A night at one of this account's properties. Looked up within the linked
     * properties only, so a night from anywhere else is not found.
     */
    private function session(int $sessionId): SurveillanceSession
    {
        $session = SurveillanceSession::query()
            ->whereIn('customer_id', Auth::user()->clientProperties()->select('id'))
            ->find($sessionId);

        abort_if($session === null, 404);

        return $session;
    }
}; ?>

<section class="w-full space-y-8">
    @if ($this->properties->isEmpty())
        <flux:callout icon="building-office-2">
            <flux:callout.heading>{{ __('No properties yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('When a professional invites you to see the nights they record at your home, the invitation link will add the property here.') }}</flux:callout.text>
        </flux:callout>
    @else
        @foreach ($this->properties as $property)
            <div wire:key="property-{{ $property->id }}" data-test="portal-property">
                <div class="mb-3">
                    <flux:heading size="lg">{{ $property->name }}</flux:heading>
                    <flux:text class="mt-1">
                        @if ($property->address)
                            {{ $property->address }} ·
                        @endif
                        {{ __('Watched by :name', ['name' => $property->user->name]) }}
                    </flux:text>
                </div>

                @if ($property->surveillanceSessions->isEmpty())
                    <flux:text>{{ __('No nights recorded here yet.') }}</flux:text>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Night') }}</flux:table.column>
                            <flux:table.column>{{ __('Room') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                            <flux:table.column>{{ __('Sightings') }}</flux:table.column>
                            <flux:table.column>{{ __('Started') }}</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($property->surveillanceSessions as $session)
                                <flux:table.row wire:key="session-{{ $session->id }}">
                                    @if ($this->editingId === $session->id)
                                        <flux:table.cell colspan="2">
                                            <form wire:submit="saveSession" class="flex flex-wrap items-center gap-2">
                                                <flux:input wire:model="sessionName" size="sm" class="max-w-52" :placeholder="__('Night of Sep 14')" data-test="session-name-input" />
                                                <flux:input wire:model="sessionRoom" size="sm" class="max-w-40" :placeholder="__('Kitchen')" maxlength="{{ Room::NAME_MAX_LENGTH }}" data-test="session-room-input" />
                                                <flux:button type="submit" size="sm" variant="primary" data-test="save-session-button">
                                                    {{ __('Save') }}
                                                </flux:button>
                                                <flux:button type="button" size="sm" variant="subtle" wire:click="cancelEdit">
                                                    {{ __('Cancel') }}
                                                </flux:button>
                                            </form>
                                        </flux:table.cell>
                                    @else
                                        <flux:table.cell variant="strong">{{ $session->name }}</flux:table.cell>
                                        <flux:table.cell>{{ $session->room?->name ?? '—' }}</flux:table.cell>
                                    @endif
                                    <flux:table.cell>
                                        <flux:badge size="sm" :color="match ($session->status) {
                                            SurveillanceSessionStatus::Pending => 'zinc',
                                            SurveillanceSessionStatus::Active => 'green',
                                            SurveillanceSessionStatus::Completed => 'blue',
                                            SurveillanceSessionStatus::Aborted => 'red',
                                        }">{{ ucfirst($session->status->value) }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $session->confirmed_tracks_count }}</flux:table.cell>
                                    <flux:table.cell>{{ $session->started_at?->diffForHumans() ?? '—' }}</flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex justify-end">
                                            <flux:button
                                                size="sm"
                                                variant="subtle"
                                                icon="pencil-square"
                                                wire:click="startEdit({{ $session->id }})"
                                                data-test="edit-session-button"
                                            />
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>
        @endforeach
    @endif
</section>
