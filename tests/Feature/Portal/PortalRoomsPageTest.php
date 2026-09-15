<?php

use App\Models\Customer;
use App\Models\SurveillanceSession;
use App\Models\User;
use Livewire\Livewire;

test('the page lists only the rooms of this account\'s linked properties', function () {
    $client = User::factory()->create();
    $professional = User::factory()->professional()->create();
    $mine = Customer::factory()->for($professional)->linkedTo($client)->create();
    $theirs = Customer::factory()->for($professional)->create();
    SurveillanceSession::factory()->for($professional)->inRoom('Kitchen')->create(['customer_id' => $mine->id]);
    SurveillanceSession::factory()->for($professional)->inRoom('Someone elses garage')->create(['customer_id' => $theirs->id]);
    SurveillanceSession::factory()->for($professional)->inRoom('The pros own attic')->create(['customer_id' => null]);
    SurveillanceSession::factory()->for($client)->inRoom('My own basement')->create();

    $this->actingAs($client)
        ->get(route('portal.rooms'))
        ->assertOk()
        ->assertSee('Kitchen')
        ->assertDontSee('Someone elses garage')
        ->assertDontSee('The pros own attic')
        ->assertDontSee('My own basement');
});

test('an account with no rooms sees an empty state', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('portal.rooms'))
        ->assertOk()
        ->assertSee('No rooms yet');
});

test('renaming a room updates every night at the property filed in it', function () {
    $client = User::factory()->create();
    $customer = Customer::factory()->linkedTo($client)->create();
    $first = SurveillanceSession::factory()->inRoom('Kitchan')->create(['user_id' => $customer->user_id, 'customer_id' => $customer->id]);
    $second = SurveillanceSession::factory()->inRoom('Kitchan')->create(['user_id' => $customer->user_id, 'customer_id' => $customer->id]);
    $elsewhere = SurveillanceSession::factory()->inRoom('Kitchan')->create(['user_id' => $customer->user_id, 'customer_id' => null]);

    $component = Livewire::actingAs($client)->test('pages::portal.rooms');
    $roomId = $component->instance()->rooms->firstWhere('name', 'Kitchan')->id;

    $component
        ->call('startRename', $roomId)
        ->assertSet('roomName', 'Kitchan')
        ->set('roomName', 'Kitchen')
        ->call('renameRoom')
        ->assertHasNoErrors();

    expect($first->refresh()->room?->name)->toBe('Kitchen')
        ->and($second->refresh()->room?->name)->toBe('Kitchen')
        ->and($elsewhere->refresh()->room?->name)->toBe('Kitchan');
});

test('the client and the professional edit the same room', function () {
    $client = User::factory()->create();
    $customer = Customer::factory()->linkedTo($client)->create();
    $session = SurveillanceSession::factory()->inRoom('Kitchen')->create(['user_id' => $customer->user_id, 'customer_id' => $customer->id]);

    $clientsRooms = Livewire::actingAs($client)->test('pages::portal.rooms')->instance()->rooms;
    $professionalsRooms = Livewire::actingAs($customer->user)->test('pages::dashboard.rooms')->instance()->rooms;

    expect($clientsRooms->pluck('id')->all())->toBe([$session->room_id])
        ->and($professionalsRooms->pluck('id')->all())->toBe([$session->room_id]);
});

test('a renamed room cannot be left blank', function () {
    $client = User::factory()->create();
    $customer = Customer::factory()->linkedTo($client)->create();
    $session = SurveillanceSession::factory()->inRoom('Kitchen')->create(['user_id' => $customer->user_id, 'customer_id' => $customer->id]);

    Livewire::actingAs($client)
        ->test('pages::portal.rooms')
        ->call('startRename', $session->room_id)
        ->set('roomName', '')
        ->call('renameRoom')
        ->assertHasErrors(['roomName' => 'required']);

    expect($session->refresh()->room?->name)->toBe('Kitchen');
});

test('removing a room keeps the nights themselves', function () {
    $client = User::factory()->create();
    $customer = Customer::factory()->linkedTo($client)->create();
    $session = SurveillanceSession::factory()->inRoom('Kitchen')->create(['user_id' => $customer->user_id, 'customer_id' => $customer->id]);

    Livewire::actingAs($client)
        ->test('pages::portal.rooms')
        ->call('removeRoom', $session->room_id);

    expect(SurveillanceSession::find($session->id))->not->toBeNull()
        ->and($session->refresh()->room_id)->toBeNull();
});

test('a room at a property not linked to this account cannot be removed, even with its id', function () {
    $professional = User::factory()->professional()->create();
    $theirs = SurveillanceSession::factory()->for($professional)->inRoom('Kitchen')->create(['customer_id' => Customer::factory()->for($professional)->create()->id]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::portal.rooms')
        ->call('removeRoom', $theirs->room_id)
        ->assertNotFound();

    expect($theirs->refresh()->room?->name)->toBe('Kitchen');
});
