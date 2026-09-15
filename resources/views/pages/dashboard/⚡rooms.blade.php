<?php

use App\Actions\Surveillance\ManageRooms;
use App\Models\Room;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Only this account's rooms, its own and its customers', so another account's
 * room id simply is not found here and the lookup 404s.
 */
new #[Title('Rooms'), Layout('layouts::app', [
    'heading' => 'Rooms',
    'subHeading' => 'The rooms your nights were recorded in. Renaming one updates every night filed in it.',
])] class extends Component {
    public ?int $editingId = null;

    public string $roomName = '';

    /**
     * @return Collection<int, Room>
     */
    #[Computed]
    public function rooms(): Collection
    {
        return app(ManageRooms::class)->ownedBy(Auth::user());
    }

    public function startRename(int $roomId): void
    {
        $this->editingId = $roomId;
        $this->roomName = $this->room($roomId)->name;
        $this->resetValidation();
    }

    public function cancelRename(): void
    {
        $this->reset('editingId', 'roomName');
        $this->resetValidation();
    }

    public function renameRoom(ManageRooms $manageRooms): void
    {
        $validated = $this->validate(['roomName' => ['required', 'string', 'max:'.Room::NAME_MAX_LENGTH]]);

        $manageRooms->rename($this->room((int) $this->editingId), $validated['roomName']);

        $this->cancelRename();
        unset($this->rooms);

        Flux::toast(variant: 'success', text: __('Room renamed.'));
    }

    public function removeRoom(ManageRooms $manageRooms, int $roomId): void
    {
        $manageRooms->remove($this->room($roomId));

        if ($this->editingId === $roomId) {
            $this->cancelRename();
        }

        unset($this->rooms);

        Flux::toast(text: __('Room removed.'));
    }

    /**
     * Look one up by id. Only rooms this page can see are searched, so an id
     * from anywhere else simply is not found.
     */
    private function room(int $roomId): Room
    {
        $room = $this->rooms->firstWhere('id', $roomId);

        abort_if($room === null, 404);

        return $room;
    }
}; ?>

<section class="w-full">
    @php($showCustomer = $this->rooms->contains(fn (Room $room) => $room->customer_id !== null))

    @if ($this->rooms->isEmpty())
        <flux:callout icon="map-pin">
            <flux:callout.heading>{{ __('No rooms yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Name the room when you start a session and it will show up here, ready to correct.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Room') }}</flux:table.column>
                @if ($showCustomer)
                    <flux:table.column>{{ __('Customer') }}</flux:table.column>
                @endif
                <flux:table.column>{{ __('Nights') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->rooms as $room)
                    <flux:table.row wire:key="room-{{ $room->id }}">
                        <flux:table.cell variant="strong">
                            @if ($this->editingId === $room->id)
                                <form wire:submit="renameRoom" class="flex items-center gap-2">
                                    <flux:input wire:model="roomName" size="sm" class="max-w-44" maxlength="{{ Room::NAME_MAX_LENGTH }}" data-test="room-name-input" />
                                    <flux:button type="submit" size="sm" variant="primary" data-test="save-room-button">
                                        {{ __('Save') }}
                                    </flux:button>
                                    <flux:button type="button" size="sm" variant="subtle" wire:click="cancelRename">
                                        {{ __('Cancel') }}
                                    </flux:button>
                                </form>
                            @else
                                {{ $room->name }}
                            @endif
                        </flux:table.cell>
                        @if ($showCustomer)
                            <flux:table.cell>{{ $room->customer?->name ?? '—' }}</flux:table.cell>
                        @endif
                        <flux:table.cell>{{ $room->surveillance_sessions_count }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-2">
                                <flux:button
                                    size="sm"
                                    variant="subtle"
                                    icon="pencil-square"
                                    wire:click="startRename({{ $room->id }})"
                                    data-test="rename-room-button"
                                />
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    icon="x-mark"
                                    wire:click="removeRoom({{ $room->id }})"
                                    wire:confirm="{{ __('Remove this room from :count nights? The recordings are kept.', ['count' => $room->surveillance_sessions_count]) }}"
                                    data-confirm-label="{{ __('Remove room') }}"
                                    data-confirm-destructive
                                    data-test="remove-room-button"
                                />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <flux:text class="mt-4 text-sm">
            {{ __('Renaming onto a room you already have merges the two, which is how a typo gets cleaned up. Rooms are kept separate per customer, so the same room name in two properties stays two rooms.') }}
        </flux:text>
    @endif
</section>
