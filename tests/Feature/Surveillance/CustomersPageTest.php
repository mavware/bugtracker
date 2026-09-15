<?php

use App\Models\Customer;
use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('surveillance.customers'))->assertRedirect(route('login'));
});

test('a homeowner is refused: customers are a professional feature', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('surveillance.customers'))
        ->assertForbidden();
});

test('an admin has the professional features too', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('surveillance.customers'))
        ->assertOk();
});

test('the page heading and subheading are rendered by the app layout', function () {
    $this->actingAs(User::factory()->professional()->create())
        ->get(route('surveillance.customers'))
        ->assertSeeInOrder([
            'Customers',
            'Properties you watch on someone else&#039;s behalf. Nights are only ever compared within one customer.',
            '<section wire:snapshot=',
        ], false);
});

test('the page lists only the current user\'s customers', function () {
    $user = User::factory()->professional()->create();
    Customer::factory()->for($user)->create(['name' => 'The Alvarez house']);
    Customer::factory()->create(['name' => 'Another firm\'s account']);

    $this->actingAs($user)
        ->get(route('surveillance.customers'))
        ->assertSee('The Alvarez house')
        ->assertDontSee('Another firm\'s account');
});

test('a customer can be added', function () {
    $user = User::factory()->professional()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.customers')
        ->set('name', 'The Alvarez house')
        ->set('address', '12 Oak Street')
        ->call('save')
        ->assertHasNoErrors();

    $customer = $user->customers()->sole();
    expect($customer->name)->toBe('The Alvarez house')
        ->and($customer->address)->toBe('12 Oak Street');
});

test('a customer needs a name', function () {
    $user = User::factory()->professional()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.customers')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);

    expect($user->customers()->count())->toBe(0);
});

test('the same customer name cannot be added twice, but two users may share one', function () {
    $user = User::factory()->professional()->create();
    Customer::factory()->for($user)->create(['name' => 'The Alvarez house']);
    Customer::factory()->create(['name' => 'The Alvarez house']);

    Livewire::actingAs($user)
        ->test('pages::dashboard.customers')
        ->set('name', 'The Alvarez house')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);

    expect($user->customers()->count())->toBe(1);
});

test('a customer can be renamed without tripping its own uniqueness rule', function () {
    $user = User::factory()->professional()->create();
    $customer = Customer::factory()->for($user)->create(['name' => 'The Alvarez house', 'address' => '12 Oak Street']);

    Livewire::actingAs($user)
        ->test('pages::dashboard.customers')
        ->call('edit', $customer->id)
        ->assertSet('name', 'The Alvarez house')
        ->set('address', '14 Oak Street')
        ->call('save')
        ->assertHasNoErrors();

    expect($customer->refresh()->address)->toBe('14 Oak Street')
        ->and($customer->name)->toBe('The Alvarez house');
});

test('removing a customer keeps their recorded nights and un-groups them', function () {
    $user = User::factory()->professional()->create();
    $customer = Customer::factory()->for($user)->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create(['customer_id' => $customer->id]);

    Livewire::actingAs($user)
        ->test('pages::dashboard.customers')
        ->call('deleteCustomer', $customer->id);

    expect(Customer::query()->find($customer->id))->toBeNull()
        ->and($session->refresh()->customer_id)->toBeNull();
});

test('another user\'s customer cannot be edited', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs(User::factory()->professional()->create())
        ->test('pages::dashboard.customers')
        ->call('edit', $customer->id);
})->throws(ModelNotFoundException::class);

test('another user\'s customer cannot be removed', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs(User::factory()->professional()->create())
        ->test('pages::dashboard.customers')
        ->call('deleteCustomer', $customer->id);
})->throws(ModelNotFoundException::class);

test('a customer can be invited to the portal, and the link shown until it is cancelled', function () {
    $user = User::factory()->professional()->create();
    $customer = Customer::factory()->for($user)->create();

    $component = Livewire::actingAs($user)
        ->test('pages::dashboard.customers')
        ->assertSeeHtml('data-test="invite-client-button"')
        ->call('invite', $customer->id)
        ->assertHasNoErrors();

    $token = $customer->refresh()->portal_invite_token;
    expect($token)->not->toBeNull();

    $component
        ->assertSeeHtml('data-test="invitation-link"')
        ->assertSee(route('portal.invitations.show', ['token' => $token]))
        ->call('revokeInvitation', $customer->id)
        ->assertDontSeeHtml('data-test="invitation-link"')
        ->assertSeeHtml('data-test="invite-client-button"');

    expect($customer->refresh()->portal_invite_token)->toBeNull();
});

test('a linked customer shows their account and can have access removed', function () {
    $user = User::factory()->professional()->create();
    $client = User::factory()->create(['email' => 'alvarez@example.com']);
    $customer = Customer::factory()->for($user)->linkedTo($client)->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.customers')
        ->assertSee('alvarez@example.com')
        ->assertDontSeeHtml('data-test="invite-client-button"')
        ->call('unlinkClient', $customer->id)
        ->assertDontSee('alvarez@example.com')
        ->assertSeeHtml('data-test="invite-client-button"');

    expect($customer->refresh()->client_user_id)->toBeNull()
        ->and(User::find($client->id))->not->toBeNull();
});

test('another user\'s customer cannot be invited', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs(User::factory()->professional()->create())
        ->test('pages::dashboard.customers')
        ->call('invite', $customer->id);
})->throws(ModelNotFoundException::class);
