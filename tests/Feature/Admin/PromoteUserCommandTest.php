<?php

use App\Enums\UserRole;
use App\Models\User;

test('it grants admin access by email', function () {
    $user = User::factory()->create(['email' => 'dana@example.com']);

    $this->artisan('user:promote', ['email' => 'dana@example.com'])
        ->expectsOutputToContain('is now a site admin')
        ->assertSuccessful();

    expect($user->refresh()->role)->toBe(UserRole::Admin);
});

test('it grants the professional role with the role option', function () {
    $user = User::factory()->create(['email' => 'dana@example.com']);

    $this->artisan('user:promote', ['email' => 'dana@example.com', '--role' => 'professional'])
        ->expectsOutputToContain('is now a professional')
        ->assertSuccessful();

    expect($user->refresh()->role)->toBe(UserRole::Professional);
});

test('it makes the account a homeowner with the demote flag', function () {
    $user = User::factory()->admin()->create(['email' => 'dana@example.com']);

    $this->artisan('user:promote', ['email' => 'dana@example.com', '--demote' => true])
        ->expectsOutputToContain('the account is a homeowner')
        ->assertSuccessful();

    expect($user->refresh()->role)->toBe(UserRole::Homeowner);
});

test('it refuses a role it does not know', function () {
    $user = User::factory()->create(['email' => 'dana@example.com']);

    $this->artisan('user:promote', ['email' => 'dana@example.com', '--role' => 'superuser'])
        ->expectsOutputToContain('The role must be one of')
        ->assertFailed();

    expect($user->refresh()->role)->toBe(UserRole::Homeowner);
});

test('it fails when no such user exists', function () {
    $this->artisan('user:promote', ['email' => 'nobody@example.com'])
        ->expectsOutputToContain('No user with the email')
        ->assertFailed();
});
