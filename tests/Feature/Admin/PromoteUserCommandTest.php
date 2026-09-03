<?php

use App\Models\User;

test('it grants admin access by email', function () {
    $user = User::factory()->create(['email' => 'dana@example.com']);

    $this->artisan('user:promote', ['email' => 'dana@example.com'])
        ->expectsOutputToContain('is now a site admin')
        ->assertSuccessful();

    expect($user->refresh()->is_admin)->toBeTrue();
});

test('it revokes admin access with the demote flag', function () {
    $user = User::factory()->admin()->create(['email' => 'dana@example.com']);

    $this->artisan('user:promote', ['email' => 'dana@example.com', '--demote' => true])
        ->expectsOutputToContain('no longer a site admin')
        ->assertSuccessful();

    expect($user->refresh()->is_admin)->toBeFalse();
});

test('it fails when no such user exists', function () {
    $this->artisan('user:promote', ['email' => 'nobody@example.com'])
        ->expectsOutputToContain('No user with the email')
        ->assertFailed();
});
