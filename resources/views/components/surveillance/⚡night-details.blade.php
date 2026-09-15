<?php

use App\Models\Customer;
use App\Models\Room;
use App\Models\SurveillanceSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The room and customer of a night, edited on the capture page before it starts.
 * Each change saves on its own: there is no form to submit on a page whose one
 * button is Start. It lives in the panel's setup column, so it leaves with the
 * rest of the setup reading the moment the night begins.
 */
new class extends Component {
    public SurveillanceSession $session;

    /**
     * The name of the room the camera is watching. Typed rather than picked:
     * it is looked up, or created, among the rooms of the night's property, so
     * grouping nights by room keeps the entry point map honest — it only makes
     * sense to merge nights shot from one spot.
     */
    public string $room = '';

    /** Whose property this is, when watching on someone else's behalf. */
    public string $customer = '';

    public function mount(SurveillanceSession $session): void
    {
        Gate::authorize('update', $session);

        $this->session = $session;
        $this->room = (string) $session->room?->name;
        $this->customer = (string) $session->customer_id;
    }

    public function updatedRoom(): void
    {
        $this->save();
    }

    public function updatedCustomer(): void
    {
        $this->save();
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
     * The customer is saved first: a room belongs to a property, so the room
     * name is then filed under the property the night now belongs to.
     */
    private function save(): void
    {
        Gate::authorize('update', $this->session);

        $validated = $this->validate([
            'room' => ['nullable', 'string', 'max:'.Room::NAME_MAX_LENGTH],
            'customer' => [
                'nullable', 'integer',
                Rule::exists('customers', 'id')->where('user_id', Auth::id()),
            ],
        ]);

        $this->session->update([
            'customer_id' => $validated['customer'] !== '' ? (int) $validated['customer'] : null,
        ]);

        $this->session->moveToRoomNamed($validated['room']);

        // The card's Alpine listens for this to show "Saved" for a moment.
        $this->dispatch('night-details-saved');
    }
}; ?>

{{-- "Saved" eases in on the server's night-details-saved event and eases out
     again a couple of seconds later; touching a field hides it at once so it
     never sits beside "Saving…". --}}
<div
    class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700"
    data-test="night-details"
    x-data="{ saved: false, timer: null }"
    x-on:night-details-saved.window="saved = true; clearTimeout(timer); timer = setTimeout(() => saved = false, 2000)"
    x-on:input="saved = false"
    x-on:change="saved = false"
>
    <div class="flex items-center justify-between gap-3">
        <flux:heading size="sm">{{ __('This night') }}</flux:heading>
        <flux:text size="sm" wire:loading wire:target="room, customer" data-test="night-details-saving">{{ __('Saving…') }}</flux:text>
        <flux:text
            size="sm"
            class="!dark:text-green-400 !text-green-600"
            x-cloak
            x-show="saved"
            x-transition.opacity.duration.300ms
            data-test="night-details-saved"
        >{{ __('Saved') }}</flux:text>
    </div>
    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
        {{ __('Name the room the camera is in — nights shot from one room are merged into one entry point map. Changes save on their own.') }}
    </p>

    <div class="mt-4 space-y-3">
        @if ($this->customers->isNotEmpty())
            <flux:select wire:model.live="customer" :label="__('Customer')" data-test="session-customer">
                <flux:select.option value="">{{ __('No customer') }}</flux:select.option>
                @foreach ($this->customers as $customerOption)
                    <flux:select.option value="{{ $customerOption->id }}">{{ $customerOption->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
        <flux:input
            wire:model.live.debounce.500ms="room"
            :label="__('Room')"
            :placeholder="__('Kitchen')"
            maxlength="{{ Room::NAME_MAX_LENGTH }}"
            data-test="session-room"
        />
    </div>
</div>
