<?php

use App\Models\Customer;
use App\Models\Room;
use App\Models\SurveillanceSession;
use App\Models\User;
use Livewire\Livewire;

test('rooms are listed per owner and property', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $customer = Customer::factory()->for($owner)->create(['name' => 'The Alvarez house']);
    SurveillanceSession::factory()->count(2)->for($owner)->inRoom('Kitchen')->create(['customer_id' => $customer->id]);
    SurveillanceSession::factory()->for($owner)->inRoom('Kitchen')->create(['customer_id' => null]);
    SurveillanceSession::factory()->create();

    $rooms = Livewire::actingAs($admin)->test('pages::admin.rooms')->instance()->rooms;

    expect($rooms)->toHaveCount(2)
        ->and($rooms->pluck('surveillance_sessions_count')->sort()->values()->all())->toBe([1, 2])
        ->and($rooms->map(fn (Room $room) => $room->customer?->name)->filter()->all())->toContain('The Alvarez house');
});

test('the page heading and subheading are rendered by the app layout', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.rooms'))
        ->assertSeeInOrder([
            'Rooms',
            'Every room recorded in, grouped by who recorded it and where.',
            '<section wire:snapshot=',
        ], false);
});

test('renaming a room only touches that owner and property\'s sessions', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $customer = Customer::factory()->for($owner)->create();
    $renamed = SurveillanceSession::factory()->for($owner)->inRoom('Kitchan')->create(['customer_id' => $customer->id]);
    $sameNameElsewhere = SurveillanceSession::factory()->for($owner)->inRoom('Kitchan')->create(['customer_id' => null]);
    $otherUsersRoom = SurveillanceSession::factory()->inRoom('Kitchan')->create();

    $component = Livewire::actingAs($admin)->test('pages::admin.rooms');
    $roomId = $component->instance()->rooms->firstWhere('customer_id', $customer->id)->id;

    $component
        ->call('startRename', $roomId)
        ->set('roomName', 'Kitchen')
        ->call('renameRoom')
        ->assertHasNoErrors();

    expect($renamed->refresh()->room?->name)->toBe('Kitchen')
        ->and($sameNameElsewhere->refresh()->room?->name)->toBe('Kitchan')
        ->and($otherUsersRoom->refresh()->room?->name)->toBe('Kitchan');
});

test('a renamed room needs a name', function () {
    $admin = User::factory()->admin()->create();
    $session = SurveillanceSession::factory()->inRoom('Kitchen')->create();

    $component = Livewire::actingAs($admin)->test('pages::admin.rooms');

    $component
        ->call('startRename', $session->room_id)
        ->set('roomName', '')
        ->call('renameRoom')
        ->assertHasErrors(['roomName' => 'required']);

    expect($session->refresh()->room?->name)->toBe('Kitchen');
});

test('removing a room keeps the sessions', function () {
    $admin = User::factory()->admin()->create();
    $session = SurveillanceSession::factory()->inRoom('Kitchen')->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.rooms')
        ->call('removeRoom', $session->room_id);

    expect(SurveillanceSession::find($session->id))->not->toBeNull()
        ->and($session->refresh()->room_id)->toBeNull();
});

test('an unknown room is a 404', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.rooms')
        ->call('removeRoom', 999999)
        ->assertNotFound();
});
