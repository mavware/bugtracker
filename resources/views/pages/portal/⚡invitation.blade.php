<?php

use App\Actions\Portal\CustomerPortalAccess;
use App\Models\Customer;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Where an invitation link lands. The route needs a signed-in account, so a
 * visitor without one is sent through login or registration and back here;
 * accepting then links that account to the property. Nothing is linked on
 * arrival — the page says which account is about to be used first.
 */
new #[Title('Invitation'), Layout('layouts::app', [
    'heading' => 'You have been invited',
])] class extends Component {
    #[Locked]
    public string $token = '';

    public function mount(string $token): void
    {
        $this->token = $token;

        $this->customer();
    }

    public function accept(CustomerPortalAccess $portalAccess): void
    {
        $portalAccess->accept($this->customer(), Auth::user());

        Flux::toast(variant: 'success', text: __('Property added to your portal.'));

        $this->redirectRoute('portal.index');
    }

    /**
     * The property this link opens. A spent, revoked or expired link is not
     * found, whichever it was: the page has nothing to say about it.
     */
    public function customer(): Customer
    {
        $customer = app(CustomerPortalAccess::class)->findByToken($this->token);

        abort_if($customer === null, 404);

        return $customer->loadMissing('user:id,name');
    }
}; ?>

<section class="w-full max-w-xl">
    @php($customer = $this->customer())

    <flux:callout icon="building-office-2">
        <flux:callout.heading>{{ $customer->name }}</flux:callout.heading>
        <flux:callout.text>
            {{ __(':name is watching this property for bugs and would like you to see the nights they record. Accepting adds it to the client portal of the account you are signed in with, :email.', [
                'name' => $customer->user->name,
                'email' => Auth::user()->email,
            ]) }}
        </flux:callout.text>
    </flux:callout>

    <flux:error name="invitation" class="mt-4" />

    <div class="mt-6 flex flex-wrap items-center gap-3">
        <flux:button variant="primary" wire:click="accept" data-test="accept-invitation-button">
            {{ __('Add to my portal') }}
        </flux:button>
        <flux:button variant="subtle" href="{{ route('dashboard') }}">
            {{ __('Not now') }}
        </flux:button>
    </div>
</section>
