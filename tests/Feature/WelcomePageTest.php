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

// The hero is the watch panel itself, not a picture of one: a night can be started
// from the first screen, with no account, and the hero's own start button forwards
// to the panel's. The guest is still told what an account adds, in the box that
// stays up all night.
test('guests can start a night from the hero', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('id="capture-app"', false)
        ->assertSee('data-test="start-capture-button"', false)
        ->assertSee('data-test="watch-hero-start"', false)
        ->assertSee('data-test="watch-hero-copy"', false)
        ->assertSee('id="local-nights"', false)
        ->assertSee('Find out what walks through the kitchen at 3am.')
        ->assertSee('to keep your nights and see trends across them.')
        ->assertDontSee('data-test="watch-signed-in-notice"', false);
});

test('a signed-in visitor is told a night started here stays on the device', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-test="watch-signed-in-notice"', false)
        ->assertSee('data-test="watch-hero-start"', false);
});

test('authenticated users are sent to the dashboard instead', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('home'));

    $response->assertOk()
        ->assertSee(route('dashboard'))
        ->assertSee('data-test="welcome-dashboard-link"', false)
        ->assertDontSee('data-test="welcome-login-link"', false)
        ->assertDontSee('data-test="welcome-register-link"', false);
});

// The hero is the page's picture of what a night looks like, so its one trail has
// to survive template edits. Counted within the hero and not across the page:
// anywhere else the same room is drawn would otherwise satisfy this on its own.
test('the hero draws the sample trail once', function () {
    $content = $this->get(route('home'))->assertOk()->getContent();

    $hero = Str::after($content, 'data-test="welcome-hero-preview"');

    expect(substr_count(Str::before($hero, '</section>'), 'data-test="sample-room-trail"'))->toBe(1);
});

// capture.js makes [data-app-nav] inert while recording. Leaving the page ends the
// night, so every link on it has to be behind the lock: the header, the sign-up
// offer in the panel, and the whole of the marketing copy below the hero.
test('the welcome chrome and the marketing sections carry the hook capture.js locks while recording', function () {
    $content = $this->get(route('home'))->assertOk()->getContent();

    // Counted as elements, not occurrences: a valueless Blade attribute renders
    // as data-app-nav="data-app-nav", so the raw string appears twice per tag.
    preg_match_all('/<[a-z-]+[^>]*\sdata-app-nav[=\s>]/i', $content, $marked);

    $locked = Str::after($content, '<div data-app-nav');

    expect($marked[0])->toHaveCount(3)
        ->and($locked)->toContain('Three steps, one night')
        ->and($locked)->toContain('It is a camera inside your home.');
});
