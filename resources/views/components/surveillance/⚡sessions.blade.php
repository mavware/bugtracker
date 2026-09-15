<?php

use App\Enums\SurveillanceSessionStatus;
use App\Models\Customer;
use App\Models\SurveillanceSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    /** Matches a session name, its room, or the customer it belongs to. */
    public string $search = '';

    public string $status = '';

    /** A customer id, or 'none' for nights filed under nobody. */
    public string $customerFilter = '';

    /** A room label, or 'none' for nights without one. */
    public string $roomFilter = '';

    /**
     * Column key => the expression the list orders by. A pending night has no
     * started_at yet, so "Started" falls back to created_at to keep a night just
     * set up at the top of the list rather than sunk under every finished one.
     *
     * @var array<string, string>
     */
    private const SORTABLE_COLUMNS = [
        'name' => 'name',
        'customer' => 'customer_name',
        'room' => 'room',
        'status' => 'status',
        'tracks' => 'tracks_count',
        'started' => 'COALESCE(started_at, created_at)',
    ];

    public string $sortBy = 'started';

    public string $sortDirection = 'desc';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedCustomerFilter(): void
    {
        $this->resetPage();
    }

    public function updatedRoomFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Clicking the column already sorted flips its direction; any other column
     * starts descending for dates and counts, ascending for text.
     */
    public function sort(string $column): void
    {
        if (! array_key_exists($column, self::SORTABLE_COLUMNS)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = in_array($column, ['tracks', 'started'], true) ? 'desc' : 'asc';
        }

        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, SurveillanceSession>
     */
    #[Computed]
    public function sessions(): LengthAwarePaginator
    {
        $sortBy = array_key_exists($this->sortBy, self::SORTABLE_COLUMNS) ? $this->sortBy : 'started';
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        return Auth::user()->surveillanceSessions()
            ->with('customer')
            ->withCount('tracks')
            ->addSelect(['customer_name' => Customer::select('name')->whereColumn('customers.id', 'surveillance_sessions.customer_id')])
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->customerFilter === 'none', fn (Builder $query) => $query->whereNull('customer_id'))
            ->when($this->customerFilter !== '' && $this->customerFilter !== 'none', fn (Builder $query) => $query->where('customer_id', (int) $this->customerFilter))
            ->when($this->roomFilter === 'none', fn (Builder $query) => $query->whereNull('room'))
            ->when($this->roomFilter !== '' && $this->roomFilter !== 'none', fn (Builder $query) => $query->where('room', $this->roomFilter))
            ->when($this->search !== '', fn (Builder $query) => $query->where(
                fn (Builder $search) => $search
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('room', 'like', "%{$this->search}%")
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', "%{$this->search}%"))
            ))
            ->orderByRaw(self::SORTABLE_COLUMNS[$sortBy].' '.$direction)
            ->orderByDesc('id')
            ->paginate(15);
    }

    /** How many of the folded-away selects are narrowing the list; search is not one. */
    public function filterCount(): int
    {
        return count(array_filter([$this->status, $this->customerFilter, $this->roomFilter], fn (string $value) => $value !== ''));
    }

    public function filtersActive(): bool
    {
        return $this->search !== '' || $this->status !== '' || $this->customerFilter !== '' || $this->roomFilter !== '';
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'customerFilter', 'roomFilter');
        $this->resetPage();
    }

    /**
     * Every room label on this account's nights, for the room filter.
     *
     * @return Collection<int, string>
     */
    #[Computed]
    public function rooms(): Collection
    {
        return Auth::user()->surveillanceSessions()
            ->whereNotNull('room')
            ->distinct()
            ->orderBy('room')
            ->pluck('room');
    }

    /**
     * claim.js dispatches this after importing a device-local night from the
     * dashboard panel. Nothing to do but render again: the query re-runs and
     * the new session is in it.
     */
    #[On('night-imported')]
    public function refreshAfterImport(): void {}

    /**
     * @return Collection<int, Customer>
     */
    #[Computed]
    public function customers(): Collection
    {
        return Auth::user()->customers()->orderBy('name')->get();
    }

    /**
     * Create a pending session and send the user to the capture page, where its
     * room and customer are set. Both start out as the latest night's: most
     * nights are shot from the same spot as the one before, so the usual case
     * needs no typing at all.
     */
    public function startSession(): void
    {
        $latest = Auth::user()->surveillanceSessions()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['room', 'customer_id']);

        $session = Auth::user()->surveillanceSessions()->create([
            'name' => __('Night of :date', ['date' => SurveillanceSession::nightDateFor(now())->format('M j')]),
            'room' => $latest?->room,
            'customer_id' => $latest?->customer_id,
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

{{-- The title is the dashboard's, above the panels. The header row is fixed:
     search on the left, Start on the right, whatever the list holds. The filter
     selects fold away behind a Filters button and open on their own when one is
     in effect, so a filtered list is never a mystery after a reload. Alpine keeps
     the open state across Livewire re-renders. --}}
<section class="w-full" x-data="{ filtersOpen: @js($this->filterCount() > 0) }">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                size="sm"
                :placeholder="__('Search by night, room or customer')"
                class="w-full max-w-xs"
                data-test="session-search"
            />
            <flux:button
                size="sm"
                variant="subtle"
                icon="funnel"
                x-on:click="filtersOpen = !filtersOpen"
                x-bind:aria-expanded="filtersOpen"
                aria-controls="session-filters"
                data-test="toggle-filters-button"
            >
                {{ __('Filters') }}
                @if ($this->filterCount() > 0)
                    <span class="ms-1 rounded-full bg-zinc-800 px-1.5 text-[10px] font-semibold text-white tabular-nums dark:bg-white dark:text-zinc-900" data-test="filter-count">{{ $this->filterCount() }}</span>
                @endif
            </flux:button>
        </div>

        <form wire:submit="startSession">
            <flux:button size="sm" type="submit" variant="primary" icon="video-camera" data-test="start-session-button">
                {{ __('Start New Session') }}
            </flux:button>
        </form>
    </div>

    <div id="session-filters" x-show="filtersOpen" x-collapse x-cloak data-test="session-filters">
        <div class="mt-3 flex flex-wrap items-center gap-3 rounded-xl">
            <flux:select wire:model.live="status" size="sm" class="max-w-40" data-test="status-filter">
                <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
                @foreach (SurveillanceSessionStatus::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ ucfirst($case->value) }}</flux:select.option>
                @endforeach
            </flux:select>
            @if ($this->customers->isNotEmpty())
                <flux:select wire:model.live="customerFilter" size="sm" class="max-w-48" data-test="customer-filter">
                    <flux:select.option value="">{{ __('Any customer') }}</flux:select.option>
                    <flux:select.option value="none">{{ __('No customer') }}</flux:select.option>
                    @foreach ($this->customers as $customerOption)
                        <flux:select.option value="{{ $customerOption->id }}">{{ $customerOption->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            @if ($this->rooms->isNotEmpty())
                <flux:select wire:model.live="roomFilter" size="sm" class="max-w-48" data-test="room-filter">
                    <flux:select.option value="">{{ __('Any room') }}</flux:select.option>
                    <flux:select.option value="none">{{ __('No room') }}</flux:select.option>
                    @foreach ($this->rooms as $roomOption)
                        <flux:select.option value="{{ $roomOption }}">{{ $roomOption }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            @if ($this->filtersActive())
                <flux:button size="sm" variant="subtle" wire:click="clearFilters" data-test="clear-filters-button">
                    {{ __('Clear') }}
                </flux:button>
            @endif
        </div>
    </div>

    @if ($this->sessions->isEmpty())
        <flux:text>
            {{ $this->filtersActive()
                ? __('No sessions match that search.')
                : __('No sessions yet. Point a camera at the room and start one.') }}
        </flux:text>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')" data-test="sort-name">{{ __('Session') }}</flux:table.column>
                @if ($this->customers->isNotEmpty())
                    <flux:table.column sortable :sorted="$sortBy === 'customer'" :direction="$sortDirection" wire:click="sort('customer')" data-test="sort-customer">{{ __('Customer') }}</flux:table.column>
                @endif
                <flux:table.column sortable :sorted="$sortBy === 'room'" :direction="$sortDirection" wire:click="sort('room')" data-test="sort-room">{{ __('Room') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" data-test="sort-status">{{ __('Status') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tracks'" :direction="$sortDirection" wire:click="sort('tracks')" data-test="sort-tracks">{{ __('Tracks') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'started'" :direction="$sortDirection" wire:click="sort('started')" data-test="sort-started">{{ __('Started') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->sessions as $session)
                    <flux:table.row wire:key="session-{{ $session->id }}">
                        <flux:table.cell variant="strong">
                            {{-- Only a pending night goes to the camera; a recording one has a page of its own. --}}
                            @if ($session->status === SurveillanceSessionStatus::Pending)
                                <flux:link variant="ghost" href="{{ route('surveillance.capture', $session) }}">{{ $session->name }}</flux:link>
                            @else
                                <flux:link variant="ghost" href="{{ route('surveillance.report', $session) }}">{{ $session->name }}</flux:link>
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
                                data-confirm-label="{{ __('Delete session') }}"
                                data-confirm-destructive
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
