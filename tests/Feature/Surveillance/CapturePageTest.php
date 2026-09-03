<?php

use App\Models\SurveillanceSession;
use App\Models\User;
use Livewire\Livewire;

// The check-label span is asserted alongside the buttons because capture.js
// rewrites it to toggle the preview: if Flux's button markup ever swallows it,
// the toggle silently stops updating and nothing else would catch it.
test('the owner can view the capture page for a pending session', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->create();

    route('surveillance.tracks.store', $session)
        |> (fn ($x) => str_replace('/', '\/', $x))
        |> (fn ($x) => $this->actingAs($user)->get(route('surveillance.capture', $session))->assertOk()->assertSee(
            'data-test="start-capture-button"',
            false
        )->assertSee('data-test="check-camera-button"', false)->assertSee(
            'data-capture="check-label"',
            false
        )->assertSee('data-test="end-session-button"', false)->assertSee(
            'data-test="abort-session-button"',
            false
        )->assertSee($x, false));
});

// A slept screen stops the camera and loses the rest of the night, so the fix has
// to be on the page they set the device up on, not buried in help.
test('the capture page says how to stop the screen sleeping', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('surveillance.capture', $session))
        ->assertSee('If the screen keeps sleeping')
        ->assertSee('Auto-Lock')
        ->assertSee('Screen timeout')
        ->assertSee('Low Power Mode');
});

// A slept or hijacked capture device can only be noticed from somewhere else, so
// the address has to be in front of the user before they walk out of the room.
test('the capture page points the user at their dashboard on another device', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('surveillance.capture', $session))
        ->assertSee('Checking on it later')
        ->assertSee('a different phone or computer', false)
        ->assertSee(route('dashboard'));
});

// capture.js makes [data-app-nav] inert while recording. Flux owns the markup for
// both regions, so nothing but this catches the attribute being dropped.
test('the app chrome carries the hook capture.js locks while recording', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->create();

    $page = $this->actingAs($user)->get(route('surveillance.capture', $session));

    // Counted as elements, not occurrences: a valueless Blade attribute renders
    // as data-app-nav="data-app-nav", so the raw string appears twice per tag.
    preg_match_all('/<[a-z-]+[^>]*\sdata-app-nav[=\s>]/i', $page->getContent(), $marked);

    expect($marked[0])->toHaveCount(2);
});

/**
 * Asserted exactly, both ways. Nothing else can catch drift here: the only consumer
 * is resources/js/surveillance/capture.js, which these tests cannot execute. A key
 * added here and never read is dead weight in every page load; one removed that the
 * script still needs breaks capture at runtime with nothing to warn us.
 */
test('the capture config carries exactly what the capture script reads', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->create();

    $config = Livewire::actingAs($user)
        ->test('pages::surveillance.capture', ['session' => $session])
        ->instance()->captureConfig();

    expect(array_keys($config))->toBe(['csrfToken', 'routes'])
        ->and(array_keys($config['routes']))->toBe(['reference', 'tracks', 'heartbeat', 'end'])
        ->and($config['routes']['tracks'])->toBe(route('surveillance.tracks.store', $session));
});

test('the capture page returns 403 for another user\'s session', function () {
    $session = SurveillanceSession::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('surveillance.capture', $session))
        ->assertForbidden();
});

test('guests are redirected to the login page', function () {
    $session = SurveillanceSession::factory()->create();

    $this->get(route('surveillance.capture', $session))->assertRedirect(route('login'));
});

test('a finished session redirects from capture to its report', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create();

    $this->actingAs($user)
        ->get(route('surveillance.capture', $session))
        ->assertRedirect(route('surveillance.report', $session));
});
