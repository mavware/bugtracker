<?php

use App\Enums\SurveillanceSessionStatus;
use App\Models\Customer;
use App\Models\SurveillanceSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    /**
     * Which room the camera is watching. Grouping nights by room keeps the entry
     * point map honest — it only makes sense to merge nights shot from one spot.
     */
    public string $room = '';

    /** Whose property this is, when watching on someone else's behalf. */
    public string $customer = '';

    /** Matches a session name, its room, or the customer it belongs to. */
    public string $search = '';

    public string $status = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $latest = Auth::user()->surveillanceSessions()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['room', 'customer_id']);

        $this->room = (string) $latest?->room;
        $this->customer = (string) $latest?->customer_id;
    }

    /**
     * @return LengthAwarePaginator<int, SurveillanceSession>
     */
    #[Computed]
    public function sessions(): LengthAwarePaginator
    {
        return Auth::user()->surveillanceSessions()
            ->with('customer')
            ->withCount('tracks')
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->search !== '', fn (Builder $query) => $query->where(
                fn (Builder $search) => $search
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('room', 'like', "%{$this->search}%")
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', "%{$this->search}%"))
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function filtersActive(): bool
    {
        return $this->search !== '' || $this->status !== '';
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status');
        $this->resetPage();
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
     * Create a pending session and send the user to the capture page.
     */
    public function startSession(): void
    {
        $validated = $this->validate([
            'room' => ['nullable', 'string', 'max:80'],
            'customer' => [
                'nullable', 'integer',
                Rule::exists('customers', 'id')->where('user_id', Auth::id()),
            ],
        ]);

        $session = Auth::user()->surveillanceSessions()->create([
            'name' => __('Night of :date', ['date' => SurveillanceSession::nightDateFor(now())->format('M j')]),
            'room' => trim($validated['room']) !== '' ? trim($validated['room']) : null,
            'customer_id' => $validated['customer'] !== '' ? (int) $validated['customer'] : null,
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

        // Emptying the last page would otherwise strand the user on a blank one.
        if ($this->sessions->isEmpty() && $this->sessions->currentPage() > 1) {
            $this->resetPage();
            unset($this->sessions);
        }
    }
}; ?>

<section class="w-full">
    <div>
        <flux:heading size="lg">{{ __('Surveillance') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Overnight bug watching sessions') }}</flux:text>
    </div>

    <form wire:submit="startSession" class="mt-4 flex flex-wrap items-end gap-3">
        @if ($this->customers->isNotEmpty())
            <flux:select wire:model="customer" :label="__('Customer')" class="max-w-52" data-test="session-customer">
                <flux:select.option value="">{{ __('No customer') }}</flux:select.option>
                @foreach ($this->customers as $customerOption)
                    <flux:select.option value="{{ $customerOption->id }}">{{ $customerOption->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
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

    @if ($this->sessions->total() > 0 || $this->filtersActive())
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                size="sm"
                :placeholder="__('Search by night, room or customer')"
                class="max-w-xs flex-1"
                data-test="session-search"
            />
            <flux:select wire:model.live="status" size="sm" class="max-w-40" data-test="status-filter">
                <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
                @foreach (SurveillanceSessionStatus::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ ucfirst($case->value) }}</flux:select.option>
                @endforeach
            </flux:select>
            @if ($this->filtersActive())
                <flux:button size="sm" variant="subtle" wire:click="clearFilters" data-test="clear-filters-button">
                    {{ __('Clear') }}
                </flux:button>
            @endif
        </div>
    @endif

    @if ($this->sessions->isEmpty())
        <flux:text>
            {{ $this->filtersActive()
                ? __('No sessions match that search.')
                : __('No sessions yet. Point a camera at the room and start one.') }}
        </flux:text>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Session') }}</flux:table.column>
                @if ($this->customers->isNotEmpty())
                    <flux:table.column>{{ __('Customer') }}</flux:table.column>
                @endif
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
                        @if ($this->customers->isNotEmpty())
                            <flux:table.cell>{{ $session->customer?->name ?? '—' }}</flux:table.cell>
                        @endif
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

        @if ($this->sessions->hasPages())
            <div class="mt-4">{{ $this->sessions->links() }}</div>
        @endif
    @endif
</section>
