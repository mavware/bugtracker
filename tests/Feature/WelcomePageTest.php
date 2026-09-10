<?php

use App\Models\User;
use Illuminate\Support\Str;

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

// The hero preview is decorative, but it is the page's picture of what a finished
// report looks like, so its one trail has to survive template edits. Counted within
// the hero and not across the page: anywhere else the same room is drawn would
// otherwise satisfy this on its own.
test('the hero preview draws the sample trail', function () {
    $content = $this->get(route('home'))->assertOk()->getContent();

    $hero = Str::after($content, 'data-test="welcome-hero-preview"');

    expect(substr_count(Str::before($hero, '</section>'), 'data-test="sample-room-trail"'))->toBe(1);
});
