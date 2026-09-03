<?php

use App\Actions\Surveillance\RoomLabel;
use App\Actions\Surveillance\RoomLabels;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admin · Rooms')] class extends Component {
    public ?string $editingKey = null;

    public string $roomName = '';

    /**
     * Every room label on the site, across all accounts.
     *
     * @return Collection<int, RoomLabel>
     */
    #[Computed]
    public function roomGroups(): Collection
    {
        return app(RoomLabels::class)->groups();
    }

    public function startRename(string $key): void
    {
        $group = $this->group($key);

        $this->editingKey = $key;
        $this->roomName = $group->room;
        $this->resetValidation();
    }

    public function cancelRename(): void
    {
        $this->reset('editingKey', 'roomName');
        $this->resetValidation();
    }

    public function renameRoom(RoomLabels $roomLabels): void
    {
        $validated = $this->validate(['roomName' => ['required', 'string', 'max:80']]);

        $roomLabels->rename($this->group((string) $this->editingKey), $validated['roomName']);

        $this->cancelRename();
        unset($this->roomGroups);

        Flux::toast(variant: 'success', text: __('Room renamed.'));
    }

    public function clearRoom(RoomLabels $roomLabels, string $key): void
    {
        $roomLabels->clear($this->group($key));

        if ($this->editingKey === $key) {
            $this->cancelRename();
        }

        unset($this->roomGroups);

        Flux::toast(text: __('Room label removed.'));
    }

    /**
     * Look one up by key. Only groups this page can see are searched, so a key
     * from anywhere else simply is not found.
     */
    private function group(string $key): RoomLabel
    {
        $group = $this->roomGroups->firstWhere('key', $key);

        abort_if($group === null, 404);

        return $group;
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Rooms') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Room labels in use, grouped by who recorded them and where.') }}</flux:text>
        </div>
        <flux:button href="{{ route('dashboard') }}" icon="arrow-left">{{ __('Dashboard') }}</flux:button>
    </div>

    @if ($this->roomGroups->isEmpty())
        <flux:text class="mt-6">{{ __('No sessions have been given a room label yet.') }}</flux:text>
    @else
        <flux:table class="mt-6">
            <flux:table.columns>
                <flux:table.column>{{ __('Room') }}</flux:table.column>
                <flux:table.column>{{ __('Owner') }}</flux:table.column>
                <flux:table.column>{{ __('Customer') }}</flux:table.column>
                <flux:table.column>{{ __('Sessions') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->roomGroups as $group)
                    <flux:table.row wire:key="room-{{ $group->key }}">
                        <flux:table.cell variant="strong">
                            @if ($this->editingKey === $group->key)
                                <form wire:submit="renameRoom" class="flex items-center gap-2">
                                    <flux:input wire:model="roomName" size="sm" class="max-w-44" data-test="room-name-input" />
                                    <flux:button type="submit" size="sm" variant="primary" data-test="save-room-button">
                                        {{ __('Save') }}
                                    </flux:button>
                                    <flux:button type="button" size="sm" variant="subtle" wire:click="cancelRename">
                                        {{ __('Cancel') }}
                                    </flux:button>
                                </form>
                            @else
                                {{ $group->room }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $group->owner }}</flux:table.cell>
                        <flux:table.cell>{{ $group->customer ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $group->sessionsCount }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-2">
                                <flux:button
                                    size="sm"
                                    variant="subtle"
                                    icon="pencil-square"
                                    wire:click="startRename('{{ $group->key }}')"
                                    data-test="rename-room-button"
                                />
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    icon="x-mark"
                                    wire:click="clearRoom('{{ $group->key }}')"
                                    wire:confirm="{{ __('Remove this room label from :count sessions? The recordings are kept.', ['count' => $group->sessionsCount]) }}"
                                    data-test="clear-room-button"
                                />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
