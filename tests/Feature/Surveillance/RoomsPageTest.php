<?php

use App\Models\Customer;
use App\Models\SurveillanceSession;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('surveillance.rooms'))->assertRedirect(route('login'));
});

test('the page lists only the labels on this account\'s nights', function () {
    $user = User::factory()->create();
    SurveillanceSession::factory()->for($user)->create(['room' => 'Kitchen']);
    SurveillanceSession::factory()->create(['room' => 'Someone elses garage']);

    $this->actingAs($user)
        ->get(route('surveillance.rooms'))
        ->assertSee('Kitchen')
        ->assertDontSee('Someone elses garage');
});

test('renaming a label updates every night that carries it', function () {
    $user = User::factory()->create();
    $first = SurveillanceSession::factory()->for($user)->create(['room' => 'Kitchan']);
    $second = SurveillanceSession::factory()->for($user)->create(['room' => 'Kitchan']);

    $component = Livewire::actingAs($user)->test('pages::surveillance.rooms');
    $key = $component->instance()->roomGroups->firstWhere('room', 'Kitchan')->key;

    $component
        ->call('startRename', $key)
        ->assertSet('roomName', 'Kitchan')
        ->set('roomName', 'Kitchen')
        ->call('renameRoom')
        ->assertHasNoErrors();

    expect($first->refresh()->room)->toBe('Kitchen')
        ->and($second->refresh()->room)->toBe('Kitchen');
});

test('the same label at another property is left alone', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $atCustomer = SurveillanceSession::factory()->for($user)->create(['room' => 'Kitchen', 'customer_id' => $customer->id]);
    $atHome = SurveillanceSession::factory()->for($user)->create(['room' => 'Kitchen', 'customer_id' => null]);

    $component = Livewire::actingAs($user)->test('pages::surveillance.rooms');
    $key = $component->instance()->roomGroups->firstWhere('customerId', $customer->id)->key;

    $component
        ->call('startRename', $key)
        ->set('roomName', 'Galley')
        ->call('renameRoom');

    expect($atCustomer->refresh()->room)->toBe('Galley')
        ->and($atHome->refresh()->room)->toBe('Kitchen');
});

test('renaming onto a label already in use merges the two', function () {
    $user = User::factory()->create();
    SurveillanceSession::factory()->count(2)->for($user)->create(['room' => 'Kitchen']);
    SurveillanceSession::factory()->for($user)->create(['room' => 'Kitchan']);

    $component = Livewire::actingAs($user)->test('pages::surveillance.rooms');
    $key = $component->instance()->roomGroups->firstWhere('room', 'Kitchan')->key;

    $component->call('startRename', $key)->set('roomName', 'Kitchen')->call('renameRoom');

    $groups = Livewire::actingAs($user)->test('pages::surveillance.rooms')->instance()->roomGroups;
    expect($groups)->toHaveCount(1)
        ->and($groups->first()->sessionsCount)->toBe(3);
});

test('a renamed label cannot be left blank', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->create(['room' => 'Kitchen']);

    $component = Livewire::actingAs($user)->test('pages::surveillance.rooms');
    $key = $component->instance()->roomGroups->firstWhere('room', 'Kitchen')->key;

    $component
        ->call('startRename', $key)
        ->set('roomName', '')
        ->call('renameRoom')
        ->assertHasErrors(['roomName' => 'required']);

    expect($session->refresh()->room)->toBe('Kitchen');
});

test('clearing a label keeps the nights themselves', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->create(['room' => 'Kitchen']);

    $component = Livewire::actingAs($user)->test('pages::surveillance.rooms');
    $key = $component->instance()->roomGroups->firstWhere('room', 'Kitchen')->key;

    $component->call('clearRoom', $key);

    expect(SurveillanceSession::find($session->id))->not->toBeNull()
        ->and($session->refresh()->room)->toBeNull();
});

test('another account\'s room cannot be renamed, even with its key', function () {
    $owner = User::factory()->create();
    $theirs = SurveillanceSession::factory()->for($owner)->create(['room' => 'Kitchen']);
    $foreignKey = Livewire::actingAs($owner)
        ->test('pages::surveillance.rooms')
        ->instance()->roomGroups->firstWhere('room', 'Kitchen')->key;

    Livewire::actingAs(User::factory()->create())
        ->test('pages::surveillance.rooms')
        ->call('clearRoom', $foreignKey)
        ->assertNotFound();

    expect($theirs->refresh()->room)->toBe('Kitchen');
});
