<?php

use App\Actions\Portal\CustomerPortalAccess;
use App\Models\Customer;
use App\Models\User;
use Livewire\Livewire;

test('a guest is sent to the login page and back to the link after signing in', function () {
    $customer = Customer::factory()->invited()->create();

    $this->get(route('portal.invitations.show', ['token' => $customer->portal_invite_token]))
        ->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe(route('portal.invitations.show', ['token' => $customer->portal_invite_token]));
});

test('the invitation names the property, the professional and the account about to be linked', function () {
    $professional = User::factory()->professional()->create(['name' => 'Pat the Exterminator']);
    $customer = Customer::factory()->for($professional)->invited()->create(['name' => 'The Alvarez house']);
    $client = User::factory()->create(['email' => 'alvarez@example.com']);

    $this->actingAs($client)
        ->get(route('portal.invitations.show', ['token' => $customer->portal_invite_token]))
        ->assertOk()
        ->assertSee('The Alvarez house')
        ->assertSee('Pat the Exterminator')
        ->assertSee('alvarez@example.com')
        ->assertSee('data-test="accept-invitation-button"', false);
});

test('accepting links the account to the property and spends the link', function () {
    $customer = Customer::factory()->invited()->create();
    $client = User::factory()->create();
    $token = $customer->portal_invite_token;

    Livewire::actingAs($client)
        ->test('pages::portal.invitation', ['token' => $token])
        ->call('accept')
        ->assertHasNoErrors()
        ->assertRedirect(route('portal.index'));

    expect($customer->refresh()->client_user_id)->toBe($client->id)
        ->and($customer->portal_invite_token)->toBeNull()
        ->and($client->isPortalClient())->toBeTrue();

    $this->actingAs(User::factory()->create())
        ->get(route('portal.invitations.show', ['token' => $token]))
        ->assertNotFound();
});

test('a link that is unknown, revoked or expired is not found', function (Closure $token) {
    $token = $token();

    $this->actingAs(User::factory()->create())
        ->get(route('portal.invitations.show', ['token' => $token]))
        ->assertNotFound();
})->with([
    'unknown' => fn (): string => 'no-such-token',
    'revoked' => function (): string {
        $customer = Customer::factory()->invited()->create();
        $token = $customer->portal_invite_token;
        app(CustomerPortalAccess::class)->revoke($customer);

        return $token;
    },
    'expired' => fn (): string => Customer::factory()->invited()->create([
        'portal_invited_at' => now()->subDays(CustomerPortalAccess::INVITATION_DAYS + 1),
    ])->portal_invite_token,
]);

test('a professional cannot accept their own customer\'s link', function () {
    $professional = User::factory()->professional()->create();
    $customer = Customer::factory()->for($professional)->invited()->create();

    Livewire::actingAs($professional)
        ->test('pages::portal.invitation', ['token' => $customer->portal_invite_token])
        ->call('accept')
        ->assertHasErrors('invitation');

    expect($customer->refresh()->client_user_id)->toBeNull()
        ->and($customer->portal_invite_token)->not->toBeNull();
});

test('a fresh link replaces the earlier one, and only the fresh one opens', function () {
    $customer = Customer::factory()->invited()->create();
    $stale = $customer->portal_invite_token;

    app(CustomerPortalAccess::class)->invite($customer);

    $client = User::factory()->create();

    $this->actingAs($client)
        ->get(route('portal.invitations.show', ['token' => $stale]))
        ->assertNotFound();

    $this->actingAs($client)
        ->get(route('portal.invitations.show', ['token' => $customer->refresh()->portal_invite_token]))
        ->assertOk();
});
