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

test('authenticated users are sent to the dashboard instead', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('home'));

    $response->assertOk()
        ->assertSee(route('dashboard'))
        ->assertSee('data-test="welcome-dashboard-link"', false)
        ->assertDontSee('data-test="welcome-login-link"', false)
        ->assertDontSee('data-test="welcome-register-link"', false);
});
