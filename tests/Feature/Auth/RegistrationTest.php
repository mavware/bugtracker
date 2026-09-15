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
        ->assertSeeHtml('data-test="register-role-professional"')
        ->assertDontSee('name="password_confirmation"', false);
});

test('the homeowner card is ticked before anything is chosen', function () {
    $html = $this->get(route('register'))->getContent();

    preg_match('/<ui-radio[^>]*data-test="register-role-homeowner"[^>]*>/', $html, $homeowner);
    preg_match('/<ui-radio[^>]*data-test="register-role-professional"[^>]*>/', $html, $professional);

    // The checked attribute itself, as opposed to the data-checked: styling every card carries.
    expect($homeowner[0] ?? '')->toMatch('/\schecked="checked"/')
        ->and($professional[0] ?? '')->not->toMatch('/\schecked="checked"/');
});

test('the professional card stays ticked when the form comes back with errors', function () {
    $html = $this->from(route('register'))
        ->post(route('register.store'), ['name' => '', 'email' => 'not-an-email', 'password' => 'password', 'role' => 'professional'])
        ->assertSessionHasErrors(['name', 'email'])
        ->assertRedirect(route('register'));

    $html = $this->get(route('register'))->getContent();
    preg_match('/<ui-radio[^>]*data-test="register-role-professional"[^>]*>/', $html, $professional);

    expect($professional[0] ?? '')->toMatch('/\schecked="checked"/');
});

test('registration does not ask for the password twice', function () {
    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'something-else',
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});

test(
    /**
     * @throws JsonException
     */ 'new users can register', function () {
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
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
    ])->assertSessionHasNoErrors();

    expect(User::where('email', 'test@example.com')->sole()->roles->all())->toBe([UserRole::Homeowner]);
});

test('a new account can register as a professional', function () {
    $this->post(route('register.store'), [
        'name' => 'Dana Alvarez',
        'email' => 'dana@example.com',
        'password' => 'password',
        'role' => 'professional',
    ])->assertSessionHasNoErrors();

    expect(User::where('email', 'dana@example.com')->sole()->roles->all())->toBe([UserRole::Professional]);
});

test('nobody can register as an admin', function (string $role) {
    $this->post(route('register.store'), [
        'name' => 'Dana Alvarez',
        'email' => 'dana@example.com',
        'password' => 'password',
        'role' => $role,
    ])->assertSessionHasErrors('role');

    expect(User::where('email', 'dana@example.com')->exists())->toBeFalse();
})->with(['admin', 'superuser']);
