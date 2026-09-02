<?php

use App\Models\SurveillanceSession;
use App\Models\User;

test('the owner can view the capture page for a pending session', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('surveillance.capture', $session))
        ->assertOk()
        ->assertSee('data-test="start-capture-button"', false)
        ->assertSee(str_replace('/', '\/', route('surveillance.tracks.store', $session)), false);
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
