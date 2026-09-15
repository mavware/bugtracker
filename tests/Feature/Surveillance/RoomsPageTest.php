<?php

use App\Models\Customer;
use App\Models\Intervention;
use App\Models\Room;
use App\Models\SurveillanceSession;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('surveillance.rooms'))->assertRedirect(route('login'));
});

test('the page heading and subheading are rendered by the app layout', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('surveillance.rooms'))
        ->assertSeeInOrder([
            'Rooms',
            'The rooms your nights were recorded in. Renaming one updates every night filed in it.',
            '<section wire:snapshot=',
        ], false);
});

test('the page lists only this account\'s rooms', function () {
    $user = User::factory()->create();
    Room::factory()->for($user)->create(['name' => 'Kitchen']);
    Room::factory()->create(['name' => 'Someone elses garage']);

    $this->actingAs($user)
        ->get(route('surveillance.rooms'))
        ->assertSee('Kitchen')
        ->assertDontSee('Someone elses garage');
});

test('a room with no customer belongs to the account itself', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create(['name' => 'The Alvarez house']);
    SurveillanceSession::factory()->for($user)->inRoom('Kitchen')->create();
    SurveillanceSession::factory()->for($user)->inRoom('Kitchen')->create(['customer_id' => $customer->id]);

    $rooms = $user->rooms()->orderBy('id')->get();

    expect($rooms)->toHaveCount(2)
        ->and($rooms[0]->customer_id)->toBeNull()
        ->and($rooms[1]->customer_id)->toBe($customer->id)
        ->and($rooms[0]->label())->toBe('Kitchen')
        ->and($rooms[1]->label())->toBe('Kitchen · The Alvarez house');
});

test('renaming a room updates every night filed in it', function () {
    $user = User::factory()->create();
    $first = SurveillanceSession::factory()->for($user)->inRoom('Kitchan')->create();
    $second = SurveillanceSession::factory()->for($user)->inRoom('Kitchan')->create();

    $component = Livewire::actingAs($user)->test('pages::dashboard.rooms');
    $roomId = $component->instance()->rooms->firstWhere('name', 'Kitchan')->id;

    $component
        ->call('startRename', $roomId)
        ->assertSet('roomName', 'Kitchan')
        ->set('roomName', 'Kitchen')
        ->call('renameRoom')
        ->assertHasNoErrors();

    expect($first->refresh()->room?->name)->toBe('Kitchen')
        ->and($second->refresh()->room?->name)->toBe('Kitchen')
        ->and($first->room_id)->toBe($roomId);
});

test('the same room name at another property is left alone', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $atCustomer = SurveillanceSession::factory()->for($user)->inRoom('Kitchen')->create(['customer_id' => $customer->id]);
    $atHome = SurveillanceSession::factory()->for($user)->inRoom('Kitchen')->create(['customer_id' => null]);

    $component = Livewire::actingAs($user)->test('pages::dashboard.rooms');
    $roomId = $component->instance()->rooms->firstWhere('customer_id', $customer->id)->id;

    $component
        ->call('startRename', $roomId)
        ->set('roomName', 'Galley')
        ->call('renameRoom');

    expect($atCustomer->refresh()->room?->name)->toBe('Galley')
        ->and($atHome->refresh()->room?->name)->toBe('Kitchen');
});

test('renaming onto a room already in use merges the two, interventions included', function () {
    $user = User::factory()->create();
    SurveillanceSession::factory()->count(2)->for($user)->inRoom('Kitchen')->create();
    $misspelt = SurveillanceSession::factory()->for($user)->inRoom('Kitchan')->create();
    $intervention = Intervention::factory()->for($user)->inRoom('Kitchan')->create();

    $component = Livewire::actingAs($user)->test('pages::dashboard.rooms');
    $roomId = $component->instance()->rooms->firstWhere('name', 'Kitchan')->id;

    $component->call('startRename', $roomId)->set('roomName', 'Kitchen')->call('renameRoom');

    $rooms = Livewire::actingAs($user)->test('pages::dashboard.rooms')->instance()->rooms;
    expect($rooms)->toHaveCount(1)
        ->and($rooms->first()->surveillance_sessions_count)->toBe(3)
        ->and($misspelt->refresh()->room_id)->toBe($rooms->first()->id)
        ->and($intervention->refresh()->room_id)->toBe($rooms->first()->id)
        ->and(Room::find($roomId))->toBeNull();
});

test('a renamed room cannot be left blank', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->inRoom('Kitchen')->create();

    $component = Livewire::actingAs($user)->test('pages::dashboard.rooms');
    $roomId = $component->instance()->rooms->firstWhere('name', 'Kitchen')->id;

    $component
        ->call('startRename', $roomId)
        ->set('roomName', '')
        ->call('renameRoom')
        ->assertHasErrors(['roomName' => 'required']);

    expect($session->refresh()->room?->name)->toBe('Kitchen');
});

test('removing a room keeps the nights themselves', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->inRoom('Kitchen')->create();

    $component = Livewire::actingAs($user)->test('pages::dashboard.rooms');
    $roomId = $component->instance()->rooms->firstWhere('name', 'Kitchen')->id;

    $component->call('removeRoom', $roomId);

    expect(SurveillanceSession::find($session->id))->not->toBeNull()
        ->and($session->refresh()->room_id)->toBeNull()
        ->and(Room::find($roomId))->toBeNull();
});

test('another account\'s room cannot be renamed, even with its id', function () {
    $owner = User::factory()->create();
    $theirs = SurveillanceSession::factory()->for($owner)->inRoom('Kitchen')->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::dashboard.rooms')
        ->call('removeRoom', $theirs->room_id)
        ->assertNotFound();

    expect($theirs->refresh()->room?->name)->toBe('Kitchen');
});

test('removing a customer takes the rooms of that property with it and keeps the nights', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $session = SurveillanceSession::factory()->for($user)->inRoom('Kitchen')->create(['customer_id' => $customer->id]);
    $ownRoom = Room::factory()->for($user)->create(['name' => 'Kitchen']);

    $customer->delete();

    expect(Room::find($ownRoom->id))->not->toBeNull()
        ->and($user->rooms()->count())->toBe(1)
        ->and($session->refresh()->room_id)->toBeNull()
        ->and($session->customer_id)->toBeNull();
});
