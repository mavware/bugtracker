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

// The point of leading with the panel: a visitor can start tonight's night from
// the first screen, with no account and without finding another page first.
test('the welcome page opens with the watch panel, above the pitch for it', function () {
    $content = $this->get(route('home'))->assertOk()
        ->assertSee('data-test="welcome-watch-section"', false)
        ->assertSee('data-capture="start"', false)
        ->assertSee('id="local-nights"', false)
        ->assertSee('Watch a room tonight')
        ->getContent();

    expect(strpos($content, 'data-test="welcome-watch-section"'))
        ->toBeLessThan(strpos($content, 'data-test="welcome-hero-preview"'));
});

// Every link on the page leaves it, and leaving ends the night. capture.js makes
// [data-app-nav] inert while recording, so the pitch below the panel — and the
// chrome around it — has to be marked or a stray click throws the night away.
test('the pitch around the watch panel goes inert while a night records', function () {
    $content = $this->get(route('home'))->assertOk()->getContent();

    // Counted as elements, not occurrences: a valueless Blade attribute renders
    // as data-app-nav="data-app-nav", so the raw string appears twice per tag.
    preg_match_all('/<[a-z-]+[^>]*\sdata-app-nav[=\s>]/i', $content, $marked);

    // The header, the four pitch sections, the footer, and the panel's own
    // create-an-account line.
    expect($marked[0])->toHaveCount(7);
});

// The hero preview is decorative, but it is the page's picture of what a finished
// report looks like, so the trails have to survive template edits. Counted within
// the hero and not across the page: the watch panel above it draws the same room
// as its camera placeholder, so a whole-page count would pass on either alone.
test('the hero preview draws every sample trail', function () {
    $content = $this->get(route('home'))->assertOk()->getContent();

    $hero = Str::after($content, 'data-test="welcome-hero-preview"');

    expect(substr_count(Str::before($hero, '</section>'), 'data-test="sample-room-trail"'))->toBe(3);
});
