<?php

use App\Actions\Admin\DeleteUserAccount;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Admin · Users'), Layout('layouts::app', [
    'heading' => 'Users',
    'subHeading' => 'Every account on the site.',
])] class extends Component {
    use ProfileValidationRules, WithPagination;

    public string $search = '';

    /** The account open in the editor, if any. */
    public ?int $editingId = null;

    public bool $showEditor = false;

    public string $name = '';

    public string $email = '';

    /**
     * The role values ticked in the editor. On the admin's own account the
     * admin role is kept whatever is ticked, which keeps the last admin from
     * locking everybody out.
     *
     * @var list<string>
     */
    public array $roles = [];

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
     * Grant a role the account lacks, or take away one it holds. An account may
     * hold any number. An admin may change their own roles too, except the
     * admin role itself, which keeps the last one from locking everybody out
     * by accident.
     */
    public function toggleRole(int $userId, string $role): void
    {
        $toggled = UserRole::tryFrom($role);
        abort_if($toggled === null, 422);
        abort_if($userId === Auth::id() && $toggled === UserRole::Admin, 403);

        $user = User::findOrFail($userId);
        $granted = ! $user->hasRole($toggled);

        $granted ? $user->grantRole($toggled) : $user->revokeRole($toggled);
        $user->save();

        Flux::toast(text: $granted
            ? __(':name is now a :role.', ['name' => $user->name, 'role' => strtolower($toggled->label())])
            : __(':name is no longer a :role.', ['name' => $user->name, 'role' => strtolower($toggled->label())]));
    }

    /**
     * Open the editor on an account's name and email. Any account, the
     * admin's own included: unlike a role, a name cannot lock anyone out.
     */
    public function edit(int $userId): void
    {
        $user = User::findOrFail($userId);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->roles = $user->roles->map(fn (UserRole $role): string => $role->value)->all();
        $this->resetValidation();
        $this->showEditor = true;
    }

    /**
     * Closing the dialog by any means (Cancel, Escape, the backdrop) unsets
     * showEditor, so a half-typed change is dropped rather than saved.
     */
    public function updatedShowEditor(bool $open): void
    {
        if (! $open) {
            $this->cancelEdit();
        }
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'showEditor', 'name', 'email', 'roles');
        $this->resetValidation();
    }

    public function isEditingSelf(): bool
    {
        return $this->editingId === Auth::id();
    }

    /**
     * The same rules registration and the profile page apply, with the
     * email's uniqueness checked against every account but this one. Roles
     * are saved as ticked, except that an admin keeps their own admin role.
     */
    public function save(): void
    {
        $user = User::findOrFail((int) $this->editingId);

        $validated = $this->validate([
            ...$this->profileRules($user->id),
            'roles' => ['array'],
            'roles.*' => [Rule::enum(UserRole::class)],
        ]);

        $user->fill([
            'name' => trim($validated['name']),
            'email' => $validated['email'],
        ]);

        $roles = array_map(UserRole::from(...), $validated['roles']);

        if ($this->isEditingSelf()) {
            $roles = array_filter($roles, fn (UserRole $role): bool => $role !== UserRole::Admin);

            if ($user->isAdmin()) {
                $roles[] = UserRole::Admin;
            }
        }

        $user->setRoles($roles);

        $user->save();

        $this->cancelEdit();
        unset($this->users);

        Flux::toast(variant: 'success', text: __('Account saved.'));
    }

    /**
     * Delete a user and everything they recorded, stored frames included.
     */
    public function deleteUser(int $userId, DeleteUserAccount $deleteUserAccount): void
    {
        abort_if($userId === Auth::id(), 403);

        $user = User::findOrFail($userId);
        $deleteUserAccount->handle($user);

        if ($this->editingId === $userId) {
            $this->cancelEdit();
        }

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
            <flux:table.column>{{ __('Roles') }}</flux:table.column>
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
                        {{-- The admin's own Admin box is locked: the last admin can never lock everyone out. --}}
                        <div class="flex flex-wrap gap-x-4 gap-y-1">
                            @foreach (UserRole::cases() as $roleOption)
                                <flux:checkbox
                                    :label="$roleOption->label()"
                                    :checked="$user->hasRole($roleOption)"
                                    :disabled="$user->id === auth()->id() && $roleOption === UserRole::Admin"
                                    wire:change="toggleRole({{ $user->id }}, '{{ $roleOption->value }}')"
                                    data-test="role-toggle-{{ $roleOption->value }}"
                                />
                            @endforeach
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->surveillance_sessions_count }}</flux:table.cell>
                    <flux:table.cell>{{ $user->customers_count }}</flux:table.cell>
                    <flux:table.cell>{{ $user->created_at?->format('M j, Y') ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            <flux:button
                                size="sm"
                                variant="subtle"
                                icon="pencil-square"
                                wire:click="edit({{ $user->id }})"
                                data-test="edit-user-button"
                            />
                            @if ($user->id !== auth()->id())
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
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">{{ $this->users->links() }}</div>

    <flux:modal wire:model="showEditor" focusable class="max-w-lg" data-test="edit-user-modal">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit account') }}</flux:heading>
                <flux:subheading>{{ __('The name and email this person signs in with.') }}</flux:subheading>
            </div>

            <flux:input wire:model="name" :label="__('Name')" data-test="user-name-input" />
            <flux:input wire:model="email" :label="__('Email')" type="email" data-test="user-email-input" />

            <flux:checkbox.group wire:model="roles" :label="__('Roles')" data-test="user-roles-group">
                @foreach (UserRole::cases() as $roleOption)
                    <flux:checkbox
                        :value="$roleOption->value"
                        :label="$roleOption->label()"
                        :description="$roleOption->description()"
                        :disabled="$this->isEditingSelf() && $roleOption === UserRole::Admin"
                    />
                @endforeach
                @if ($this->isEditingSelf())
                    <flux:text size="sm" class="mt-1" data-test="own-roles-note">
                        {{ __('You cannot take the admin role away from yourself. Another admin can, from this page.') }}
                    </flux:text>
                @endif
            </flux:checkbox.group>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="filled" wire:click="cancelEdit" data-test="cancel-edit-user-button">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" data-test="save-user-button">
                    {{ __('Save changes') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>
