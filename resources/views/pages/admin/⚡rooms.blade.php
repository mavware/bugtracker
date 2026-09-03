<?php

use App\Models\Customer;
use App\Models\SurveillanceSession;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admin · Rooms')] class extends Component {
    public ?string $editingKey = null;

    public string $roomName = '';

    /**
     * Rooms are a label on a session, not a table, so they are grouped by the
     * owner and property they belong to — "Kitchen" in two homes is two rooms.
     *
     * @return Collection<string, array{key: string, user_id: int, customer_id: int|null, owner: string, customer: string|null, room: string, sessions_count: int}>
     */
    #[Computed]
    public function roomGroups(): Collection
    {
        $groups = SurveillanceSession::query()
            ->whereNotNull('room')
            ->selectRaw('user_id, customer_id, room, COUNT(*) as sessions_count')
            ->groupBy('user_id', 'customer_id', 'room')
            ->orderBy('room')
            ->get();

        $owners = User::whereIn('id', $groups->pluck('user_id')->unique())->pluck('email', 'id');
        $customers = Customer::whereIn('id', $groups->pluck('customer_id')->filter()->unique())->pluck('name', 'id');

        return $groups->map(function (SurveillanceSession $group) use ($owners, $customers): array {
            $customerId = $group->customer_id !== null ? (int) $group->customer_id : null;

            return [
                'key' => sha1($group->user_id.'|'.$customerId.'|'.$group->room),
                'user_id' => (int) $group->user_id,
                'customer_id' => $customerId,
                'owner' => (string) ($owners[$group->user_id] ?? '—'),
                'customer' => $customerId !== null ? (string) ($customers[$customerId] ?? '—') : null,
                'room' => (string) $group->room,
                'sessions_count' => (int) $group->getAttribute('sessions_count'),
            ];
        })->keyBy('key');
    }

    public function startRename(string $key): void
    {
        $group = $this->group($key);

        $this->editingKey = $key;
        $this->roomName = $group['room'];
        $this->resetValidation();
    }

    public function cancelRename(): void
    {
        $this->reset('editingKey', 'roomName');
        $this->resetValidation();
    }

    /**
     * Rename every session in the group. Renaming onto an existing label merges
     * the two, which is exactly how a typo gets cleaned up.
     */
    public function renameRoom(): void
    {
        $validated = $this->validate(['roomName' => ['required', 'string', 'max:80']]);

        $this->sessionsInGroup($this->group((string) $this->editingKey))
            ->update(['room' => trim($validated['roomName'])]);

        $this->cancelRename();
        unset($this->roomGroups);

        Flux::toast(variant: 'success', text: __('Room renamed.'));
    }

    /**
     * Drop the label without touching the recordings themselves.
     */
    public function clearRoom(string $key): void
    {
        $this->sessionsInGroup($this->group($key))->update(['room' => null]);

        if ($this->editingKey === $key) {
            $this->cancelRename();
        }

        unset($this->roomGroups);

        Flux::toast(text: __('Room label removed.'));
    }

    /**
     * @return array{key: string, user_id: int, customer_id: int|null, owner: string, customer: string|null, room: string, sessions_count: int}
     */
    private function group(string $key): array
    {
        $group = $this->roomGroups->get($key);

        abort_if($group === null, 404);

        return $group;
    }

    /**
     * @param  array{key: string, user_id: int, customer_id: int|null, owner: string, customer: string|null, room: string, sessions_count: int}  $group
     * @return Builder<SurveillanceSession>
     */
    private function sessionsInGroup(array $group): Builder
    {
        return SurveillanceSession::query()
            ->where('user_id', $group['user_id'])
            ->where('room', $group['room'])
            ->when(
                $group['customer_id'] === null,
                fn (Builder $query) => $query->whereNull('customer_id'),
                fn (Builder $query) => $query->where('customer_id', $group['customer_id']),
            );
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

    <div class="mt-6">
        <x-admin.nav />
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
                    <flux:table.row wire:key="room-{{ $group['key'] }}">
                        <flux:table.cell variant="strong">
                            @if ($this->editingKey === $group['key'])
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
                                {{ $group['room'] }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $group['owner'] }}</flux:table.cell>
                        <flux:table.cell>{{ $group['customer'] ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $group['sessions_count'] }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-2">
                                <flux:button
                                    size="sm"
                                    variant="subtle"
                                    icon="pencil-square"
                                    wire:click="startRename('{{ $group['key'] }}')"
                                    data-test="rename-room-button"
                                />
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    icon="x-mark"
                                    wire:click="clearRoom('{{ $group['key'] }}')"
                                    wire:confirm="{{ __('Remove this room label from :count sessions? The recordings are kept.', ['count' => $group['sessions_count']]) }}"
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
