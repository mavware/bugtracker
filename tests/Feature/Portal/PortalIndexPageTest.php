<?php

use App\Models\BugTrack;
use App\Models\Customer;
use App\Models\SurveillanceSession;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['portal.index', 'portal.rooms']);

test('the page heading and subheading are rendered by the app layout', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('portal.index'))
        ->assertSeeInOrder([
            'Your properties',
            'The nights recorded at your home by the professional watching it.',
            '<section wire:snapshot=',
        ], false);
});

test('an account with no linked property sees an empty state and no portal in the sidebar', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('portal.index'))
        ->assertOk()
        ->assertSee('No properties yet')
        ->assertDontSee(route('portal.rooms'));
});

test('the sidebar offers the portal once a property is linked', function () {
    $client = User::factory()->create();
    Customer::factory()->linkedTo($client)->create();

    $this->actingAs($client)
        ->get(route('dashboard'))
        ->assertSee(route('portal.index'))
        ->assertSee(route('portal.rooms'));
});

test('the page lists the nights at every linked property and nothing else', function () {
    $client = User::factory()->create();
    $professional = User::factory()->professional()->create(['name' => 'Pat the Exterminator']);
    $mine = Customer::factory()->for($professional)->linkedTo($client)->create(['name' => 'The Alvarez house', 'address' => '12 Oak Street']);
    $theirs = Customer::factory()->for($professional)->create(['name' => 'The Brown house']);

    SurveillanceSession::factory()->for($professional)->completed()->inRoom('Kitchen')->create(['customer_id' => $mine->id, 'name' => 'Night of Sep 1']);
    SurveillanceSession::factory()->for($professional)->completed()->create(['customer_id' => $theirs->id, 'name' => 'Night of Sep 2']);
    SurveillanceSession::factory()->for($client)->completed()->create(['name' => 'My own night']);

    $this->actingAs($client)
        ->get(route('portal.index'))
        ->assertOk()
        ->assertSeeInOrder(['The Alvarez house', '12 Oak Street', 'Watched by Pat the Exterminator', 'Night of Sep 1', 'Kitchen'])
        ->assertDontSee('The Brown house')
        ->assertDontSee('Night of Sep 2')
        ->assertDontSee('My own night');
});

test('sightings count only confirmed tracks', function () {
    $client = User::factory()->create();
    $customer = Customer::factory()->linkedTo($client)->create();
    $session = SurveillanceSession::factory()->completed()->create(['customer_id' => $customer->id, 'user_id' => $customer->user_id]);
    BugTrack::factory()->count(2)->for($session, 'session')->create();
    BugTrack::factory()->for($session, 'session')->create(['dismissed_at' => now()]);

    $component = Livewire::actingAs($client)->test('pages::portal.index');

    expect($component->instance()->properties->first()->surveillanceSessions->first()->confirmed_tracks_count)->toBe(2);
});

test('a night\'s name and room can be edited', function () {
    $client = User::factory()->create();
    $customer = Customer::factory()->linkedTo($client)->create();
    $session = SurveillanceSession::factory()->completed()->create(['customer_id' => $customer->id, 'user_id' => $customer->user_id, 'name' => 'Night of Sep 1']);

    Livewire::actingAs($client)
        ->test('pages::portal.index')
        ->call('startEdit', $session->id)
        ->assertSet('sessionName', 'Night of Sep 1')
        ->assertSet('sessionRoom', '')
        ->set('sessionName', '  First night in the kitchen ')
        ->set('sessionRoom', ' Kitchen ')
        ->call('saveSession')
        ->assertHasNoErrors()
        ->assertSet('editingId', null);

    expect($session->refresh()->name)->toBe('First night in the kitchen')
        ->and($session->room?->name)->toBe('Kitchen')
        ->and($session->customer_id)->toBe($customer->id);
});

test('clearing the room leaves the night without one', function () {
    $client = User::factory()->create();
    $customer = Customer::factory()->linkedTo($client)->create();
    $session = SurveillanceSession::factory()->completed()->inRoom('Kitchen')->create(['customer_id' => $customer->id, 'user_id' => $customer->user_id]);

    Livewire::actingAs($client)
        ->test('pages::portal.index')
        ->call('startEdit', $session->id)
        ->set('sessionRoom', '')
        ->call('saveSession')
        ->assertHasNoErrors();

    expect($session->refresh()->room_id)->toBeNull();
});

test('a night cannot be left without a name', function () {
    $client = User::factory()->create();
    $customer = Customer::factory()->linkedTo($client)->create();
    $session = SurveillanceSession::factory()->completed()->create(['customer_id' => $customer->id, 'user_id' => $customer->user_id, 'name' => 'Night of Sep 1']);

    Livewire::actingAs($client)
        ->test('pages::portal.index')
        ->call('startEdit', $session->id)
        ->set('sessionName', '')
        ->call('saveSession')
        ->assertHasErrors(['sessionName' => 'required']);

    expect($session->refresh()->name)->toBe('Night of Sep 1');
});

test('a night at a property not linked to this account is not found', function (Closure $session) {
    $client = User::factory()->create();
    $session = $session($client);

    Livewire::actingAs($client)
        ->test('pages::portal.index')
        ->call('startEdit', $session->id)
        ->assertNotFound();

    expect($session->refresh()->name)->toBe('Untouched');
})->with([
    'another customer of the same professional' => function (User $client) {
        $professional = User::factory()->professional()->create();
        Customer::factory()->for($professional)->linkedTo($client)->create();
        $other = Customer::factory()->for($professional)->create();

        return SurveillanceSession::factory()->for($professional)->create(['customer_id' => $other->id, 'name' => 'Untouched']);
    },
    'a property whose invitation is still open' => function (User $client) {
        $customer = Customer::factory()->invited()->create();

        return SurveillanceSession::factory()->create(['user_id' => $customer->user_id, 'customer_id' => $customer->id, 'name' => 'Untouched']);
    },
    'the professional\'s own night, filed under nobody' => function (User $client) {
        $customer = Customer::factory()->linkedTo($client)->create();

        return SurveillanceSession::factory()->create(['user_id' => $customer->user_id, 'customer_id' => null, 'name' => 'Untouched']);
    },
]);

test('the professional\'s and property\'s names are escaped', function () {
    $client = User::factory()->create();
    $professional = User::factory()->professional()->create(['name' => 'Pat <script>alert(1)</script>']);
    Customer::factory()->for($professional)->linkedTo($client)->create(['name' => 'House <b>bold</b>']);

    $this->actingAs($client)
        ->get(route('portal.index'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('<b>bold</b>', false)
        ->assertSee('House &lt;b&gt;bold&lt;/b&gt;', false);
});
