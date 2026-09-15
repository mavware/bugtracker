<?php

use App\Actions\Admin\DeleteUserAccount;
use App\Enums\UserRole;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Admin · Users'), Layout('layouts::app', [
    'heading' => 'Users',
    'subHeading' => 'Every account on the site.',
])] class extends Component {
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
     * Change what an account is: homeowner, professional or admin. Admins cannot
     * change their own role, which keeps the last one from locking everybody
     * out by accident.
     */
    public function setRole(int $userId, string $role): void
    {
        abort_if($userId === Auth::id(), 403);

        $newRole = UserRole::tryFrom($role);
        abort_if($newRole === null, 422);

        $user = User::findOrFail($userId);
        $user->role = $newRole;
        $user->save();

        Flux::toast(text: __(':name is now a :role.', ['name' => $user->name, 'role' => strtolower($newRole->label())]));
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
    <flux:input
        wire:model.live.debounce.300ms="search"
        icon="magnifying-glass"
        :placeholder="__('Search by name or email')"
        class="max-w-md"
        data-test="user-search"
    />

    <flux:table class="mt-4">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
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
                    <flux:table.cell>
                        @if ($user->id === auth()->id())
                            <flux:badge size="sm" :color="$user->isAdmin() ? 'purple' : 'zinc'" data-test="own-role">{{ $user->role->label() }}</flux:badge>
                        @else
                            <flux:select
                                size="sm"
                                class="max-w-40"
                                wire:change="setRole({{ $user->id }}, $event.target.value)"
                                data-test="role-select"
                            >
                                @foreach (UserRole::cases() as $roleOption)
                                    <flux:select.option value="{{ $roleOption->value }}" :selected="$user->role === $roleOption">{{ $roleOption->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->surveillance_sessions_count }}</flux:table.cell>
                    <flux:table.cell>{{ $user->customers_count }}</flux:table.cell>
                    <flux:table.cell>{{ $user->created_at?->format('M j, Y') ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($user->id !== auth()->id())
                            <div class="flex justify-end gap-2">
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    icon="trash"
                                    wire:click="deleteUser({{ $user->id }})"
                                    wire:confirm="{{ __('Delete this account and every night it recorded? This cannot be undone.') }}"
                                    data-confirm-label="{{ __('Delete account') }}"
                                    data-confirm-destructive
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
