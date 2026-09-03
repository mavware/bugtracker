<?php

use App\Models\Customer;
use App\Models\SurveillanceSession;
use App\Models\User;
use Livewire\Livewire;

test('rooms are grouped per owner and property', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $customer = Customer::factory()->for($owner)->create(['name' => 'The Alvarez house']);
    SurveillanceSession::factory()->count(2)->for($owner)->create(['room' => 'Kitchen', 'customer_id' => $customer->id]);
    SurveillanceSession::factory()->for($owner)->create(['room' => 'Kitchen', 'customer_id' => null]);
    SurveillanceSession::factory()->create(['room' => null]);

    $groups = Livewire::actingAs($admin)->test('pages::admin.rooms')->instance()->roomGroups;

    expect($groups)->toHaveCount(2)
        ->and($groups->pluck('sessionsCount')->sort()->values()->all())->toBe([1, 2])
        ->and($groups->pluck('customer')->filter()->all())->toContain('The Alvarez house');
});

test('renaming a room only touches that owner and property\'s sessions', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $customer = Customer::factory()->for($owner)->create();
    $renamed = SurveillanceSession::factory()->for($owner)->create(['room' => 'Kitchan', 'customer_id' => $customer->id]);
    $sameLabelElsewhere = SurveillanceSession::factory()->for($owner)->create(['room' => 'Kitchan', 'customer_id' => null]);
    $otherUsersRoom = SurveillanceSession::factory()->create(['room' => 'Kitchan']);

    $component = Livewire::actingAs($admin)->test('pages::admin.rooms');
    $key = $component->instance()->roomGroups->firstWhere('customerId', $customer->id)->key;

    $component
        ->call('startRename', $key)
        ->set('roomName', 'Kitchen')
        ->call('renameRoom')
        ->assertHasNoErrors();

    expect($renamed->refresh()->room)->toBe('Kitchen')
        ->and($sameLabelElsewhere->refresh()->room)->toBe('Kitchan')
        ->and($otherUsersRoom->refresh()->room)->toBe('Kitchan');
});

test('a renamed room needs a name', function () {
    $admin = User::factory()->admin()->create();
    $session = SurveillanceSession::factory()->create(['room' => 'Kitchen']);

    $component = Livewire::actingAs($admin)->test('pages::admin.rooms');
    $key = $component->instance()->roomGroups->firstWhere('room', 'Kitchen')->key;

    $component
        ->call('startRename', $key)
        ->set('roomName', '')
        ->call('renameRoom')
        ->assertHasErrors(['roomName' => 'required']);

    expect($session->refresh()->room)->toBe('Kitchen');
});

test('clearing a room label keeps the sessions', function () {
    $admin = User::factory()->admin()->create();
    $session = SurveillanceSession::factory()->create(['room' => 'Kitchen']);

    $component = Livewire::actingAs($admin)->test('pages::admin.rooms');
    $key = $component->instance()->roomGroups->firstWhere('room', 'Kitchen')->key;

    $component->call('clearRoom', $key);

    expect(SurveillanceSession::find($session->id))->not->toBeNull()
        ->and($session->refresh()->room)->toBeNull();
});

test('an unknown room group is a 404', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.rooms')
        ->call('clearRoom', 'not-a-real-key')
        ->assertNotFound();
});
