<?php

use App\Enums\SurveillanceSessionStatus;
use App\Models\Customer;
use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the dashboard lists only the current user\'s sessions', function () {
    $user = User::factory()->create();
    SurveillanceSession::factory()->for($user)->create(['name' => 'My kitchen watch']);
    SurveillanceSession::factory()->create(['name' => 'Someone else\'s watch']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSeeLivewire('surveillance.sessions')
        ->assertSee('My kitchen watch')
        ->assertDontSee('Someone else\'s watch');
});

test('only a pending night links to the capture page; a recording one links to its own page', function () {
    $user = User::factory()->create();
    $pending = SurveillanceSession::factory()->for($user)->create();
    $recording = SurveillanceSession::factory()->for($user)->active()->create();

    Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->assertSee(route('surveillance.capture', $pending))
        ->assertSee(route('surveillance.report', $recording))
        ->assertDontSee(route('surveillance.capture', $recording));
});

test('starting a session creates a pending session and redirects to capture', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->call('startSession')
        ->assertRedirect(route('surveillance.capture', SurveillanceSession::first()));

    $session = SurveillanceSession::first();
    expect($session->user_id)->toBe($user->id)
        ->and($session->status)->toBe(SurveillanceSessionStatus::Pending);
});

test('a session started after midnight is named for the evening it began', function () {
    $this->travelTo(Carbon::parse('2026-09-02 00:30'));
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('surveillance.sessions')->call('startSession');

    expect(SurveillanceSession::first()->name)->toBe('Night of Sep 1');
});

// Most nights are shot from the same spot as the one before, so a new night opens
// on the capture page already filed where the last one was, ready to change there.
test('a new session carries over the latest night\'s room and customer', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    SurveillanceSession::factory()->for($user)->inRoom('Garage')->create(['customer_id' => null, 'created_at' => now()->subDays(2)]);
    SurveillanceSession::factory()->for($user)->inRoom('Kitchen')->create(['customer_id' => $customer->id, 'created_at' => now()->subDay()]);

    Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->call('startSession');

    $session = SurveillanceSession::latest('id')->first();
    expect($session->customer_id)->toBe($customer->id)
        ->and($session->room?->name)->toBe('Kitchen');
});

// The header row is the same whatever the list holds: a first night has to be
// startable from an empty dashboard, and the search stays put as the list grows.
test('search and Start are on the page even before the first session', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('surveillance.sessions')
        ->assertSee('data-test="session-search"', false)
        ->assertSee('data-test="start-session-button"', false)
        ->assertSee('data-test="toggle-filters-button"', false)
        ->assertDontSee('data-test="filter-count"', false);
});

test('the Filters button counts the selects in effect, not the search', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->inRoom('Kitchen')->create();

    Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->set('search', 'Kitchen')
        ->assertDontSee('data-test="filter-count"', false)
        ->set('status', SurveillanceSessionStatus::Pending->value)
        ->set('roomFilter', (string) $session->room_id)
        ->assertSeeHtml('data-test="filter-count">2<');
});

test('the dashboard no longer asks for a room or customer before starting', function () {
    $user = User::factory()->create();
    Customer::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->assertDontSee('data-test="session-room"', false)
        ->assertDontSee('data-test="session-customer"', false)
        ->assertSee('data-test="start-session-button"', false);
});

test('the list can be searched by night, room or customer', function (string $term) {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create(['name' => 'The Alvarez house']);
    SurveillanceSession::factory()->for($user)->inRoom('Kitchen')->create([
        'name' => 'Night of Sep 2',
        'customer_id' => $customer->id,
    ]);
    SurveillanceSession::factory()->for($user)->inRoom('Garage')->create([
        'name' => 'Night of Aug 30',
        'customer_id' => null,
    ]);

    Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->set('search', $term)
        ->assertSee('Night of Sep 2')
        ->assertDontSee('Night of Aug 30');
})->with([
    'by name' => 'Sep 2',
    'by room' => 'Kitchen',
    'by customer' => 'Alvarez',
]);

test('a search never reaches another account\'s sessions', function () {
    $user = User::factory()->create();
    SurveillanceSession::factory()->inRoom('Kitchen')->create(['name' => 'Someone elses Kitchen night']);

    Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->set('search', 'Kitchen')
        ->assertDontSee('Someone elses Kitchen night')
        ->assertSee('No sessions match that search');
});

test('the list can be filtered by status', function () {
    $user = User::factory()->create();
    SurveillanceSession::factory()->for($user)->active()->create(['name' => 'Recording tonight']);
    SurveillanceSession::factory()->for($user)->completed()->create(['name' => 'Finished last week']);

    Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->set('status', SurveillanceSessionStatus::Active->value)
        ->assertSee('Recording tonight')
        ->assertDontSee('Finished last week');
});

test('the list can be filtered by customer, including nights filed under nobody', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create(['name' => 'The Alvarez house']);
    SurveillanceSession::factory()->for($user)->create(['name' => 'Alvarez night', 'customer_id' => $customer->id]);
    SurveillanceSession::factory()->for($user)->create(['name' => 'Own night', 'customer_id' => null]);

    Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->set('customerFilter', (string) $customer->id)
        ->assertSee('Alvarez night')
        ->assertDontSee('Own night')
        ->set('customerFilter', 'none')
        ->assertSee('Own night')
        ->assertDontSee('Alvarez night');
});

test('the list can be filtered by room, including nights without one', function () {
    $user = User::factory()->create();
    $kitchen = SurveillanceSession::factory()->for($user)->inRoom('Kitchen')->create(['name' => 'Kitchen night']);
    SurveillanceSession::factory()->for($user)->inRoom('Garage')->create(['name' => 'Garage night']);
    SurveillanceSession::factory()->for($user)->create(['name' => 'Unlabelled night']);

    Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->assertSee('data-test="room-filter"', false)
        ->set('roomFilter', (string) $kitchen->room_id)
        ->assertSee('Kitchen night')
        ->assertDontSee('Garage night')
        ->assertDontSee('Unlabelled night')
        ->set('roomFilter', 'none')
        ->assertSee('Unlabelled night')
        ->assertDontSee('Kitchen night');
});

test('the room filter only offers this account\'s rooms', function () {
    $user = User::factory()->create();
    SurveillanceSession::factory()->for($user)->inRoom('Kitchen')->create();
    SurveillanceSession::factory()->inRoom('Someone elses attic')->create();

    $component = Livewire::actingAs($user)->test('surveillance.sessions');

    expect($component->instance()->rooms->pluck('name')->all())->toBe(['Kitchen']);
});

test('the list can be sorted by a column and the direction flips on a second click', function () {
    $user = User::factory()->create();
    SurveillanceSession::factory()->for($user)->inRoom('Kitchen')->create(['name' => 'Bravo night', 'created_at' => now()->subDay()]);
    SurveillanceSession::factory()->for($user)->inRoom('Garage')->create(['name' => 'Alpha night', 'created_at' => now()]);

    $component = Livewire::actingAs($user)->test('surveillance.sessions');

    $component->call('sort', 'room')
        ->assertSet('sortBy', 'room')
        ->assertSet('sortDirection', 'asc')
        ->assertSeeInOrder(['Alpha night', 'Bravo night']);

    $component->call('sort', 'room')
        ->assertSet('sortDirection', 'desc')
        ->assertSeeInOrder(['Bravo night', 'Alpha night']);
});

test('the list can be sorted by customer name', function () {
    $user = User::factory()->create();
    $zimmer = Customer::factory()->for($user)->create(['name' => 'Zimmer house']);
    $abbott = Customer::factory()->for($user)->create(['name' => 'Abbott house']);
    SurveillanceSession::factory()->for($user)->create(['name' => 'Zimmer night', 'customer_id' => $zimmer->id, 'created_at' => now()]);
    SurveillanceSession::factory()->for($user)->create(['name' => 'Abbott night', 'customer_id' => $abbott->id, 'created_at' => now()->subDay()]);

    Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->assertSeeInOrder(['Zimmer night', 'Abbott night'])
        ->call('sort', 'customer')
        ->assertSeeInOrder(['Abbott night', 'Zimmer night']);
});

test('an unknown sort column is ignored', function () {
    $user = User::factory()->create();
    SurveillanceSession::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->call('sort', 'password')
        ->assertSet('sortBy', 'started')
        ->assertSet('sortDirection', 'desc')
        ->assertOk();
});

test('a night not yet started still lists first by default', function () {
    $user = User::factory()->create();
    SurveillanceSession::factory()->for($user)->completed()->create(['name' => 'Finished night', 'created_at' => now()->subDay()]);
    SurveillanceSession::factory()->for($user)->create(['name' => 'Fresh pending night', 'created_at' => now()]);

    Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->assertSeeInOrder(['Fresh pending night', 'Finished night']);
});

test('sorting returns to the first page', function () {
    $user = User::factory()->create();

    foreach (range(1, 16) as $index) {
        SurveillanceSession::factory()->for($user)->create(['created_at' => now()->addMinutes($index)]);
    }

    $component = Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->set('paginators.page', 2)
        ->call('sort', 'name');

    expect($component->instance()->sessions->currentPage())->toBe(1);
});

test('searching returns to the first page', function () {
    $user = User::factory()->create();

    foreach (range(1, 16) as $index) {
        SurveillanceSession::factory()->for($user)->create([
            'name' => 'Night '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'created_at' => now()->addMinutes($index),
        ]);
    }

    $component = Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->set('paginators.page', 2)
        ->set('search', 'Night');

    expect($component->instance()->sessions->currentPage())->toBe(1);
});

test('clearing the filters brings every session back', function () {
    $user = User::factory()->create();
    SurveillanceSession::factory()->for($user)->create(['name' => 'Night of Sep 2']);
    SurveillanceSession::factory()->for($user)->create(['name' => 'Night of Aug 30']);

    Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->set('search', 'Sep')
        ->assertDontSee('Night of Aug 30')
        ->set('roomFilter', 'none')
        ->set('customerFilter', 'none')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('roomFilter', '')
        ->assertSet('customerFilter', '')
        ->assertSee('Night of Aug 30');
});

test('the list is paginated, newest first', function () {
    $user = User::factory()->create();

    foreach (range(1, 16) as $index) {
        SurveillanceSession::factory()->for($user)->create([
            'name' => 'Night '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'created_at' => now()->addMinutes($index),
        ]);
    }

    $component = Livewire::actingAs($user)->test('surveillance.sessions');

    expect($component->instance()->sessions->total())->toBe(16)
        ->and($component->instance()->sessions->count())->toBe(15);

    $component
        ->assertSee('Night 16')
        ->assertDontSee('Night 01')
        ->set('paginators.page', 2)
        ->assertSee('Night 01')
        ->assertDontSee('Night 16');
});

test('emptying the last page falls back rather than stranding the user on a blank one', function () {
    $user = User::factory()->create();

    foreach (range(1, 16) as $index) {
        SurveillanceSession::factory()->for($user)->create(['created_at' => now()->addMinutes($index)]);
    }

    $oldest = SurveillanceSession::where('user_id', $user->id)->oldest()->first();

    $component = Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->set('paginators.page', 2)
        ->call('deleteSession', $oldest->id);

    expect($component->instance()->sessions->currentPage())->toBe(1)
        ->and($component->instance()->sessions->count())->toBe(15);
});

test('deleting a session removes its rows and stored files', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create();
    Storage::disk('local')->put("surveillance/$session->id/reference.jpg", 'jpeg');

    Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->call('deleteSession', $session->id);

    expect(SurveillanceSession::find($session->id))->toBeNull();
    Storage::disk('local')->assertMissing("surveillance/$session->id/reference.jpg");
});

test('deleting another user\'s session is forbidden', function () {
    $session = SurveillanceSession::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('surveillance.sessions')
        ->call('deleteSession', $session->id)
        ->assertForbidden();

    expect(SurveillanceSession::find($session->id))->not->toBeNull();
});
