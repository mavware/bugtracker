<?php

use App\Enums\SurveillanceSessionStatus;
use App\Models\Customer;
use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the dashboard lists only the current user\'s sessions', function () {
    $user = User::factory()->create();
    $own = SurveillanceSession::factory()->for($user)->create(['name' => 'My kitchen watch']);
    $other = SurveillanceSession::factory()->create(['name' => 'Someone else\'s watch']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSeeLivewire('surveillance.sessions')
        ->assertSee('My kitchen watch')
        ->assertDontSee('Someone else\'s watch');
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

test('starting a session files it under the chosen customer and room', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->set('customer', (string) $customer->id)
        ->set('room', 'Kitchen')
        ->call('startSession')
        ->assertHasNoErrors();

    $session = SurveillanceSession::first();
    expect($session->customer_id)->toBe($customer->id)
        ->and($session->room)->toBe('Kitchen');
});

test('a session cannot be filed under another user\'s customer', function () {
    $user = User::factory()->create();
    $foreignCustomer = Customer::factory()->create();

    Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->set('customer', (string) $foreignCustomer->id)
        ->call('startSession')
        ->assertHasErrors('customer');

    expect(SurveillanceSession::count())->toBe(0);
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

    $oldest = SurveillanceSession::where('user_id', $user->id)->orderBy('created_at')->first();

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
    Storage::disk('local')->put("surveillance/{$session->id}/reference.jpg", 'jpeg');

    Livewire::actingAs($user)
        ->test('surveillance.sessions')
        ->call('deleteSession', $session->id);

    expect(SurveillanceSession::find($session->id))->toBeNull();
    Storage::disk('local')->assertMissing("surveillance/{$session->id}/reference.jpg");
});

test('deleting another user\'s session is forbidden', function () {
    $session = SurveillanceSession::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('surveillance.sessions')
        ->call('deleteSession', $session->id)
        ->assertForbidden();

    expect(SurveillanceSession::find($session->id))->not->toBeNull();
});
