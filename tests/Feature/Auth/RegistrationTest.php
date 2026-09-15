<?php

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk()
        ->assertSeeHtml('data-test="register-role-homeowner"')
        ->assertSeeHtml('data-test="register-role-professional"');
});

test(
    /**
     * @throws JsonException
     */ 'new users can register', function () {
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    });

test('a new account is a homeowner unless it says otherwise', function () {
    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    expect(User::where('email', 'test@example.com')->sole()->role)->toBe(UserRole::Homeowner);
});

test('a new account can register as a professional', function () {
    $this->post(route('register.store'), [
        'name' => 'Dana Alvarez',
        'email' => 'dana@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'professional',
    ])->assertSessionHasNoErrors();

    expect(User::where('email', 'dana@example.com')->sole()->role)->toBe(UserRole::Professional);
});

test('nobody can register as an admin', function (string $role) {
    $this->post(route('register.store'), [
        'name' => 'Dana Alvarez',
        'email' => 'dana@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => $role,
    ])->assertSessionHasErrors('role');

    expect(User::where('email', 'dana@example.com')->exists())->toBeFalse();
})->with(['admin', 'superuser']);
