<?php

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Str;

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
test('the dashboard carries the panel that imports device-local nights, hidden until the script finds some', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    // The route sits in a json_encode'd attribute, so its slashes are escaped.
    $response->assertSee('id="claim-nights"', false)
        ->assertSee('data-test="claim-nights-panel"', false)
        ->assertSee(str_replace('/', '\\/', route('surveillance.import')), false)
        ->assertSee('data-claim="row-template"', false)
        ->assertSee('data-cell="customer"', false)
        ->assertSee('data-cell="room"', false)
        ->assertSee('data-cell="import"', false)
        ->assertSee('max-h-80 overflow-y-auto', false);

    // Hidden in the markup: nothing to show until claim.js has read the store.
    expect(Str::before(Str::after($response->getContent(), 'id="claim-nights"'), '>'))->toContain('class="hidden"');
});

// Each row offers the user's own customers, and only theirs, so a night can be
// filed as it is imported.
test('the import panel is handed the user\'s customers and nobody else\'s', function () {
    $user = User::factory()->create();
    $mine = Customer::factory()->for($user)->create(['name' => 'Alvarez']);
    Customer::factory()->create(['name' => 'Someone else']);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSee('&quot;customers&quot;:[{&quot;id&quot;:'.$mine->id.',&quot;name&quot;:&quot;Alvarez&quot;}]', false)
        ->assertDontSee('Someone else');
});
