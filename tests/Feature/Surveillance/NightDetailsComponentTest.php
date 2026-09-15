<?php

use App\Models\Customer;
use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

// The fields sit in the setup column, which capture.js hides the moment the
// night starts: where the night is filed is decided before the camera opens.
test('the capture page carries the night\'s room and customer in the setup reading', function () {
    $user = User::factory()->create();
    Customer::factory()->for($user)->create(['name' => 'The Alvarez house']);
    $session = SurveillanceSession::factory()->for($user)->inRoom('Kitchen')->create();

    $response = $this->actingAs($user)
        ->get(route('surveillance.capture', $session))
        ->assertOk()
        ->assertSeeLivewire('surveillance.night-details');

    $setupHelp = Str::between($response->getContent(), 'data-capture="setup-help"', 'data-capture="night-help"');

    expect($setupHelp)
        ->toContain('data-test="session-room"')
        ->toContain('data-test="session-customer"')
        ->toContain('The Alvarez house')
        ->toContain('Changes save on their own');
});

test('changing the room saves it to the night straight away', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('surveillance.night-details', ['session' => $session])
        ->set('room', '  Kitchen ')
        ->assertHasNoErrors()
        ->assertDispatched('night-details-saved');

    expect($session->refresh()->room?->name)->toBe('Kitchen');
});

test('clearing the room leaves the night without one', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->inRoom('Kitchen')->create();

    Livewire::actingAs($user)
        ->test('surveillance.night-details', ['session' => $session])
        ->assertSet('room', 'Kitchen')
        ->set('room', '')
        ->assertHasNoErrors();

    expect($session->refresh()->room_id)->toBeNull();
});

test('changing the customer files the night under them, and clearing it under nobody', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $session = SurveillanceSession::factory()->for($user)->create(['customer_id' => null]);

    $component = Livewire::actingAs($user)
        ->test('surveillance.night-details', ['session' => $session])
        ->set('customer', (string) $customer->id)
        ->assertHasNoErrors();

    expect($session->refresh()->customer_id)->toBe($customer->id);

    $component->set('customer', '')->assertHasNoErrors();

    expect($session->refresh()->customer_id)->toBeNull();
});

test('a night cannot be filed under another user\'s customer', function () {
    $user = User::factory()->create();
    $foreignCustomer = Customer::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->create(['customer_id' => null]);

    Livewire::actingAs($user)
        ->test('surveillance.night-details', ['session' => $session])
        ->set('customer', (string) $foreignCustomer->id)
        ->assertHasErrors('customer')
        ->assertNotDispatched('night-details-saved');

    expect($session->refresh()->customer_id)->toBeNull();
});

test('a room label longer than the column is refused rather than truncated', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->inRoom('Kitchen')->create();

    Livewire::actingAs($user)
        ->test('surveillance.night-details', ['session' => $session])
        ->set('room', str_repeat('a', 81))
        ->assertHasErrors('room');

    expect($session->refresh()->room?->name)->toBe('Kitchen');
});

test('another user cannot change a night\'s details', function () {
    $session = SurveillanceSession::factory()->inRoom('Kitchen')->create();

    Livewire::actingAs(User::factory()->create())
        ->test('surveillance.night-details', ['session' => $session])
        ->assertForbidden();

    expect($session->refresh()->room?->name)->toBe('Kitchen');
});
