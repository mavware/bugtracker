<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard panel links to every section from the sidebar', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    foreach ([
        'surveillance.customers',
        'surveillance.trends',
        'surveillance.heatmap',
        'surveillance.rooms',
    ] as $route) {
        $response->assertSee(route($route));
    }
});

test('the sidebar reaches the other sections from a section page too', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('surveillance.trends'));

    $response->assertSee(route('dashboard'))
        ->assertSee(route('surveillance.rooms'));
});

// Nights recorded in this browser before the user had an account can be pulled
// in from the dashboard; claim.js reads the import route from this panel.
test('the dashboard carries the panel that imports device-local nights', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    // The route sits in a json_encode'd attribute, so its slashes are escaped.
    $response->assertSee('id="claim-nights"', false)
        ->assertSee('data-test="claim-nights-panel"', false)
        ->assertSee(str_replace('/', '\\/', route('surveillance.import')), false);
});
