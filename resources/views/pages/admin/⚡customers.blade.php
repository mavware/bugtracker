<?php

use App\Models\Customer;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Admin · Customers')] class extends Component {
    use WithPagination;

    public string $search = '';

    public ?int $editingId = null;

    public string $name = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    #[Computed]
    public function customers(): LengthAwarePaginator
    {
        return Customer::query()
            ->with('user')
            ->withCount('surveillanceSessions')
            ->when($this->search !== '', fn (Builder $query) => $query->where(
                fn (Builder $search) => $search
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('address', 'like', "%{$this->search}%")
                    ->orWhereHas('user', fn (Builder $user) => $user->where('email', 'like', "%{$this->search}%"))
            ))
            ->orderBy('name')
            ->paginate(20);
    }

    public function startRename(int $customerId): void
    {
        $customer = Customer::findOrFail($customerId);

        $this->editingId = $customer->id;
        $this->name = $customer->name;
        $this->resetValidation();
    }

    public function cancelRename(): void
    {
        $this->reset('editingId', 'name');
        $this->resetValidation();
    }

    /**
     * Names are unique per owner, so the check is scoped to the customer's account.
     */
    public function renameCustomer(): void
    {
        $customer = Customer::findOrFail($this->editingId);

        $validated = $this->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('customers', 'name')
                    ->where('user_id', $customer->user_id)
                    ->ignore($customer->id),
            ],
        ]);

        $customer->update(['name' => $validated['name']]);

        $this->cancelRename();
        unset($this->customers);

        Flux::toast(variant: 'success', text: __('Customer renamed.'));
    }

    /**
     * Removing a customer un-groups their nights; the recordings are kept.
     */
    public function deleteCustomer(int $customerId): void
    {
        Customer::findOrFail($customerId)->delete();

        if ($this->editingId === $customerId) {
            $this->cancelRename();
        }

        unset($this->customers);

        Flux::toast(text: __('Customer removed. Their recorded nights were kept.'));
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Customers') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Properties recorded on someone else\'s behalf, across all accounts.') }}</flux:text>
        </div>
        <flux:button href="{{ route('dashboard') }}" icon="arrow-left">{{ __('Dashboard') }}</flux:button>
    </div>

    <flux:input
        wire:model.live.debounce.300ms="search"
        icon="magnifying-glass"
        :placeholder="__('Search by customer, address or owner email')"
        class="mt-6 max-w-md"
        data-test="customer-search"
    />

    <flux:table class="mt-4">
        <flux:table.columns>
            <flux:table.column>{{ __('Customer') }}</flux:table.column>
            <flux:table.column>{{ __('Owner') }}</flux:table.column>
            <flux:table.column>{{ __('Address') }}</flux:table.column>
            <flux:table.column>{{ __('Sessions') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->customers as $customer)
                <flux:table.row wire:key="customer-{{ $customer->id }}">
                    <flux:table.cell variant="strong">
                        @if ($this->editingId === $customer->id)
                            <form wire:submit="renameCustomer" class="flex items-center gap-2">
                                <flux:input wire:model="name" size="sm" class="max-w-52" data-test="customer-name-input" />
                                <flux:button type="submit" size="sm" variant="primary" data-test="save-customer-button">
                                    {{ __('Save') }}
                                </flux:button>
                                <flux:button type="button" size="sm" variant="subtle" wire:click="cancelRename">
                                    {{ __('Cancel') }}
                                </flux:button>
                            </form>
                        @else
                            {{ $customer->name }}
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $customer->user?->email ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $customer->address ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $customer->surveillance_sessions_count }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            <flux:button
                                size="sm"
                                variant="subtle"
                                icon="pencil-square"
                                wire:click="startRename({{ $customer->id }})"
                                data-test="rename-customer-button"
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

    <div class="mt-4">{{ $this->customers->links() }}</div>
</section>
