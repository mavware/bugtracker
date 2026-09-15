<?php

use App\Enums\UserRole;
use App\Models\User;

test('it grants admin access by email', function () {
    $user = User::factory()->create(['email' => 'dana@example.com']);

    $this->artisan('user:promote', ['email' => 'dana@example.com'])
        ->expectsOutputToContain('is now a admin. Roles now: homeowner, admin.')
        ->assertSuccessful();

    expect($user->refresh()->roles->all())->toBe([UserRole::Homeowner, UserRole::Admin]);
});

test('it grants the professional role with the role option', function () {
    $user = User::factory()->create(['email' => 'dana@example.com']);

    $this->artisan('user:promote', ['email' => 'dana@example.com', '--role' => 'professional'])
        ->expectsOutputToContain('is now a professional')
        ->assertSuccessful();

    expect($user->refresh()->roles->all())->toBe([UserRole::Homeowner, UserRole::Professional]);
});

test('it takes the role away with the demote flag and keeps the others', function () {
    $user = User::factory()->withRoles([UserRole::Professional, UserRole::Admin])->create(['email' => 'dana@example.com']);

    $this->artisan('user:promote', ['email' => 'dana@example.com', '--demote' => true])
        ->expectsOutputToContain('is no longer a admin. Roles now: professional.')
        ->assertSuccessful();

    expect($user->refresh()->roles->all())->toBe([UserRole::Professional]);
});

test('granting a role already held changes nothing', function () {
    $user = User::factory()->admin()->create(['email' => 'dana@example.com']);

    $this->artisan('user:promote', ['email' => 'dana@example.com'])->assertSuccessful();

    expect($user->refresh()->roles->all())->toBe([UserRole::Admin]);
});

test('it refuses a role it does not know', function () {
    $user = User::factory()->create(['email' => 'dana@example.com']);

    $this->artisan('user:promote', ['email' => 'dana@example.com', '--role' => 'superuser'])
        ->expectsOutputToContain('The role must be one of')
        ->assertFailed();

    expect($user->refresh()->roles->all())->toBe([UserRole::Homeowner]);
});

test('it fails when no such user exists', function () {
    $this->artisan('user:promote', ['email' => 'nobody@example.com'])
        ->expectsOutputToContain('No user with the email')
        ->assertFailed();
});
