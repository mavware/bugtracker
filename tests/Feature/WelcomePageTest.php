<?php

use App\Models\User;

test('guests are invited to log in or register', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee(route('login'))
        ->assertSee(route('register'))
        ->assertSee('data-test="welcome-login-link"', false)
        ->assertSee('data-test="welcome-register-link"', false)
        ->assertDontSee('data-test="welcome-dashboard-link"', false);
});

// The answer to "do I have to sign up first" has to be visible to someone who
// has not signed up, and has to point at the real guest capture page.
test('guests are shown the no-account path and where it leads', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('data-test="welcome-demo-section"', false)
        ->assertSee('data-test="welcome-demo-link"', false)
        ->assertSee(route('watch.capture'))
        ->assertSee('Watch a room tonight without signing up.')
        ->assertSee('An account is what adds trends across nights');
});

test('the no-account section is not shown to someone who already has an account', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertDontSee('data-test="welcome-demo-section"', false);
});

test('authenticated users are sent to the dashboard instead', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('home'));

    $response->assertOk()
        ->assertSee(route('dashboard'))
        ->assertSee('data-test="welcome-dashboard-link"', false)
        ->assertDontSee('data-test="welcome-login-link"', false)
        ->assertDontSee('data-test="welcome-register-link"', false);
});

// The hero preview is decorative, but it is the page's only picture of what a
// finished report looks like, so the trails have to survive template edits.
test('the hero preview draws every sample trail', function () {
    $response = $this->get(route('home'));

    $response->assertOk();

    expect(substr_count($response->getContent(), 'data-test="sample-room-trail"'))->toBe(3);
});
