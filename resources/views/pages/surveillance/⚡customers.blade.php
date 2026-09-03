<?php

use App\Models\Customer;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Customers')] class extends Component {
    public ?int $editingId = null;

    public string $name = '';

    public string $address = '';

    public string $notes = '';

    /**
     * @return Collection<int, Customer>
     */
    #[Computed]
    public function customers(): Collection
    {
        return Auth::user()->customers()
            ->withCount('surveillanceSessions')
            ->orderBy('name')
            ->get();
    }

    /**
     * Load a customer into the form for editing.
     */
    public function edit(int $customerId): void
    {
        $customer = Auth::user()->customers()->findOrFail($customerId);

        $this->editingId = $customer->id;
        $this->name = $customer->name;
        $this->address = (string) $customer->address;
        $this->notes = (string) $customer->notes;
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'name', 'address', 'notes');
        $this->resetValidation();
    }

    /**
     * Create the customer, or save the one being edited.
     */
    public function save(): void
    {
        $validated = $this->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('customers', 'name')
                    ->where('user_id', Auth::id())
                    ->ignore($this->editingId),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'address' => $validated['address'] !== '' ? $validated['address'] : null,
            'notes' => $validated['notes'] !== '' ? $validated['notes'] : null,
        ];

        if ($this->editingId !== null) {
            Auth::user()->customers()->findOrFail($this->editingId)->update($attributes);
        } else {
            Auth::user()->customers()->create($attributes);
        }

        $this->cancelEdit();
        unset($this->customers);

        Flux::toast(variant: 'success', text: __('Customer saved.'));
    }

    /**
     * Remove a customer. Their recorded nights are kept, just un-grouped.
     */
    public function deleteCustomer(int $customerId): void
    {
        Auth::user()->customers()->findOrFail($customerId)->delete();

        if ($this->editingId === $customerId) {
            $this->cancelEdit();
        }

        unset($this->customers);

        Flux::toast(text: __('Customer removed. Their recorded nights were kept.'));
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Customers') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Properties you watch on someone else\'s behalf. Nights are only ever compared within one customer.') }}</flux:text>
        </div>
        <flux:button href="{{ route('dashboard') }}" icon="arrow-left">{{ __('Dashboard') }}</flux:button>
    </div>

    <form wire:submit="save" class="mt-6 flex flex-wrap items-start gap-3">
        <flux:input
            wire:model="name"
            :label="__('Name')"
            :placeholder="__('The Alvarez house')"
            class="min-w-56 flex-1"
            data-test="customer-name"
        />
        <flux:input
            wire:model="address"
            :label="__('Address')"
            :placeholder="__('12 Oak Street')"
            class="min-w-56 flex-1"
            data-test="customer-address"
        />
        <flux:button type="submit" variant="primary" class="mt-6" data-test="save-customer-button">
            {{ $this->editingId !== null ? __('Save changes') : __('Add customer') }}
        </flux:button>
        @if ($this->editingId !== null)
            <flux:button type="button" variant="subtle" class="mt-6" wire:click="cancelEdit" data-test="cancel-edit-button">
                {{ __('Cancel') }}
            </flux:button>
        @endif
    </form>

    <flux:separator class="my-6" />

    @if ($this->customers->isEmpty())
        <flux:text>{{ __('No customers yet. Add one and you can group each night\'s recording by property.') }}</flux:text>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Customer') }}</flux:table.column>
                <flux:table.column>{{ __('Address') }}</flux:table.column>
                <flux:table.column>{{ __('Nights') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->customers as $customer)
                    <flux:table.row wire:key="customer-{{ $customer->id }}">
                        <flux:table.cell variant="strong">{{ $customer->name }}</flux:table.cell>
                        <flux:table.cell>{{ $customer->address ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $customer->surveillance_sessions_count }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-2">
                                <flux:button
                                    size="sm"
                                    href="{{ route('surveillance.trends', ['customer' => $customer->id]) }}"
                                    data-test="customer-trends-link"
                                >{{ __('Trends') }}</flux:button>
                                <flux:button
                                    size="sm"
                                    variant="subtle"
                                    icon="pencil-square"
                                    wire:click="edit({{ $customer->id }})"
                                    data-test="edit-customer-button"
                                />
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    icon="trash"
                                    wire:click="deleteCustomer({{ $customer->id }})"
                                    wire:confirm="{{ __('Remove this customer? Their recorded nights are kept, but no longer grouped.') }}"
                                    data-test="delete-customer-button"
                                />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
