<?php

use App\Models\BugTrack;
use App\Models\Intervention;
use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('surveillance.trends'))->assertRedirect(route('login'));
});

test('the trends page charts each night and marks the interventions', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create(['started_at' => Carbon::parse('2026-09-01 23:00')]);
    BugTrack::factory()->count(2)->for($session, 'session')->create();
    Intervention::factory()->for($user)->create(['performed_on' => '2026-09-02', 'description' => 'Sealed the dishwasher gap']);

    $this->actingAs($user)
        ->get(route('surveillance.trends'))
        ->assertSee('Busiest night')
        ->assertSee('Sep 1')
        ->assertSee('Sealed the dishwasher gap')
        ->assertSee('trend-chart', false);
});

test('the trends page shows an empty state before any night finishes', function () {
    $user = User::factory()->create();
    SurveillanceSession::factory()->for($user)->active()->create();

    $this->actingAs($user)
        ->get(route('surveillance.trends'))
        ->assertSee('No finished nights yet');
});

test('recording an intervention attaches it to the room being viewed', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::surveillance.trends')
        ->set('room', 'Kitchen')
        ->set('performedOn', '2026-09-01')
        ->set('description', 'Placed gel bait under the sink')
        ->call('addIntervention')
        ->assertHasNoErrors();

    $intervention = $user->interventions()->sole();
    expect($intervention->room)->toBe('Kitchen')
        ->and($intervention->description)->toBe('Placed gel bait under the sink')
        ->and($intervention->performed_on->toDateString())->toBe('2026-09-01');
});

test('an intervention recorded without a room filter applies everywhere', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::surveillance.trends')
        ->set('description', 'Sealed the front door sweep')
        ->call('addIntervention')
        ->assertHasNoErrors();

    expect($user->interventions()->sole()->room)->toBeNull();
});

test('an intervention needs a description and cannot be dated in the future', function (string $field, mixed $value, string $rule) {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::surveillance.trends')
        ->set('description', 'Placed gel bait')
        ->set($field, $value)
        ->call('addIntervention')
        ->assertHasErrors([$field => $rule]);

    expect($user->interventions()->count())->toBe(0);
})->with([
    'blank description' => ['description', '', 'required'],
    'future date' => ['performedOn', '2099-01-01', 'before_or_equal'],
    'missing date' => ['performedOn', '', 'required'],
]);

test('an intervention can be removed', function () {
    $user = User::factory()->create();
    $intervention = Intervention::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::surveillance.trends')
        ->call('deleteIntervention', $intervention->id);

    expect(Intervention::find($intervention->id))->toBeNull();
});

test('another user\'s intervention cannot be removed', function () {
    $intervention = Intervention::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::surveillance.trends')
        ->call('deleteIntervention', $intervention->id);
})->throws(ModelNotFoundException::class);
