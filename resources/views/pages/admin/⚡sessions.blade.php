<?php

use App\Actions\Surveillance\ComputeSessionAnalytics;
use App\Enums\SurveillanceSessionStatus;
use App\Models\SurveillanceSession;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Admin · Sessions')] class extends Component {
    use WithPagination;

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

    /**
     * @return LengthAwarePaginator<int, SurveillanceSession>
     */
    #[Computed]
    public function sessions(): LengthAwarePaginator
    {
        return SurveillanceSession::query()
            ->with(['user', 'customer'])
            ->withCount('tracks')
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->search !== '', fn (Builder $query) => $query->where(
                fn (Builder $search) => $search
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('room', 'like', "%{$this->search}%")
                    ->orWhereHas('user', fn (Builder $user) => $user->where('email', 'like', "%{$this->search}%"))
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);
    }

    /**
     * Close out a session whose capture device never came back — otherwise it stays
     * Active forever, hogging the "recording now" panel and never reaching a report.
     */
    public function endSession(int $sessionId, ComputeSessionAnalytics $analytics): void
    {
        $session = SurveillanceSession::findOrFail($sessionId);

        abort_unless($session->status === SurveillanceSessionStatus::Active, 409);

        $session->update([
            'status' => SurveillanceSessionStatus::Completed,
            'ended_at' => $session->last_heartbeat_at ?? now(),
        ]);

        $analytics->handle($session);

        unset($this->sessions);

        Flux::toast(variant: 'success', text: __('Session closed out.'));
    }

    /**
     * Delete a session. Going through the model fires the hook that removes its
     * stored reference frame and crops.
     */
    public function deleteSession(int $sessionId): void
    {
        SurveillanceSession::findOrFail($sessionId)->delete();

        unset($this->sessions);

        Flux::toast(variant: 'success', text: __('Session deleted.'));
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Sessions') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Every night recorded, across all accounts.') }}</flux:text>
        </div>
        <flux:button href="{{ route('dashboard') }}" icon="arrow-left">{{ __('Dashboard') }}</flux:button>
    </div>

    <div class="mt-6">
        <x-admin.nav />
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            :placeholder="__('Search by session, room or owner email')"
            class="max-w-md flex-1"
            data-test="session-search"
        />
        <flux:select wire:model.live="status" class="max-w-44" data-test="status-filter">
            <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
            @foreach (SurveillanceSessionStatus::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ ucfirst($case->value) }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table class="mt-4">
        <flux:table.columns>
            <flux:table.column>{{ __('Session') }}</flux:table.column>
            <flux:table.column>{{ __('Owner') }}</flux:table.column>
            <flux:table.column>{{ __('Customer') }}</flux:table.column>
            <flux:table.column>{{ __('Room') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Sightings') }}</flux:table.column>
            <flux:table.column>{{ __('Started') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->sessions as $session)
                <flux:table.row wire:key="session-{{ $session->id }}">
                    <flux:table.cell variant="strong">{{ $session->name }}</flux:table.cell>
                    <flux:table.cell>{{ $session->user?->email ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $session->customer?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $session->room ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="match ($session->status) {
                            SurveillanceSessionStatus::Pending => 'zinc',
                            SurveillanceSessionStatus::Active => 'green',
                            SurveillanceSessionStatus::Completed => 'blue',
                            SurveillanceSessionStatus::Aborted => 'red',
                        }">{{ ucfirst($session->status->value) }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $session->tracks_count }}</flux:table.cell>
                    <flux:table.cell>{{ $session->started_at?->format('M j, H:i') ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            @if ($session->status === SurveillanceSessionStatus::Active)
                                <flux:button
                                    size="sm"
                                    variant="subtle"
                                    wire:click="endSession({{ $session->id }})"
                                    wire:confirm="{{ __('Close out this session as if the night had ended?') }}"
                                    data-test="end-session-button"
                                >{{ __('Close out') }}</flux:button>
                            @endif
                            <flux:button
                                size="sm"
                                variant="danger"
                                icon="trash"
                                wire:click="deleteSession({{ $session->id }})"
                                wire:confirm="{{ __('Delete this session and its stored images?') }}"
                                data-test="delete-session-button"
                            />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">{{ $this->sessions->links() }}</div>
</section>
