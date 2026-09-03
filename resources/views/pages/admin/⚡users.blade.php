<?php

use App\Actions\Admin\DeleteUserAccount;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Admin · Users')] class extends Component {
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->when($this->search !== '', fn (Builder $query) => $query->where(
                fn (Builder $search) => $search
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
            ))
            ->withCount(['surveillanceSessions', 'customers'])
            ->orderBy('name')
            ->paginate(20);
    }

    /**
     * Grant or revoke admin access. Admins cannot change their own, which keeps
     * the last one from locking everybody out by accident.
     */
    public function toggleAdmin(int $userId): void
    {
        abort_if($userId === Auth::id(), 403);

        $user = User::findOrFail($userId);
        $user->is_admin = ! $user->is_admin;
        $user->save();

        Flux::toast(text: $user->is_admin
            ? __(':name is now an admin.', ['name' => $user->name])
            : __(':name is no longer an admin.', ['name' => $user->name]));
    }

    /**
     * Delete a user and everything they recorded, stored frames included.
     */
    public function deleteUser(int $userId, DeleteUserAccount $deleteUserAccount): void
    {
        abort_if($userId === Auth::id(), 403);

        $user = User::findOrFail($userId);
        $deleteUserAccount->handle($user);

        unset($this->users);

        Flux::toast(variant: 'success', text: __('Account deleted.'));
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Users') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Every account on the site.') }}</flux:text>
        </div>
        <flux:button href="{{ route('dashboard') }}" icon="arrow-left">{{ __('Dashboard') }}</flux:button>
    </div>

    <div class="mt-6">
        <x-admin.nav />
    </div>

    <flux:input
        wire:model.live.debounce.300ms="search"
        icon="magnifying-glass"
        :placeholder="__('Search by name or email')"
        class="mt-6 max-w-md"
        data-test="user-search"
    />

    <flux:table class="mt-4">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Sessions') }}</flux:table.column>
            <flux:table.column>{{ __('Customers') }}</flux:table.column>
            <flux:table.column>{{ __('Joined') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->users as $user)
                <flux:table.row wire:key="user-{{ $user->id }}">
                    <flux:table.cell variant="strong">
                        {{ $user->name }}
                        @if ($user->is_admin)
                            <flux:badge size="sm" color="purple" class="ms-2">{{ __('Admin') }}</flux:badge>
                        @endif
                        @if ($user->id === auth()->id())
                            <flux:badge size="sm" color="zinc" class="ms-2">{{ __('You') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $user->email }}
                        @unless ($user->hasVerifiedEmail())
                            <flux:badge size="sm" color="amber" class="ms-2">{{ __('Unverified') }}</flux:badge>
                        @endunless
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->surveillance_sessions_count }}</flux:table.cell>
                    <flux:table.cell>{{ $user->customers_count }}</flux:table.cell>
                    <flux:table.cell>{{ $user->created_at?->format('M j, Y') ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($user->id !== auth()->id())
                            <div class="flex justify-end gap-2">
                                <flux:button
                                    size="sm"
                                    variant="subtle"
                                    wire:click="toggleAdmin({{ $user->id }})"
                                    data-test="toggle-admin-button"
                                >{{ $user->is_admin ? __('Revoke admin') : __('Make admin') }}</flux:button>
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    icon="trash"
                                    wire:click="deleteUser({{ $user->id }})"
                                    wire:confirm="{{ __('Delete this account and every night it recorded? This cannot be undone.') }}"
                                    data-test="delete-user-button"
                                />
                            </div>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">{{ $this->users->links() }}</div>
</section>
