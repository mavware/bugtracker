<?php

use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('guests are sent to the login page', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['admin.index', 'admin.users', 'admin.sessions', 'admin.rooms', 'admin.customers']);

test('an ordinary account is refused', function (string $route) {
    $this->actingAs(User::factory()->create())
        ->get(route($route))
        ->assertForbidden();
})->with(['admin.index', 'admin.users', 'admin.sessions', 'admin.rooms', 'admin.customers']);

test('an admin gets in', function (string $route) {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route($route))
        ->assertOk();
})->with(['admin.index', 'admin.users', 'admin.sessions', 'admin.rooms', 'admin.customers']);

test('only admins are offered the administration link', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertSee('Administration');

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertDontSee('Administration');
});

test('being an admin does not open another account\'s recordings', function () {
    Storage::fake('local');
    $session = SurveillanceSession::factory()->completed()->create();
    Storage::disk('local')->put("surveillance/{$session->id}/reference.jpg", 'jpeg');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('surveillance.reference.show', $session))
        ->assertForbidden();
});

test('admin access cannot be granted by mass assignment', function () {
    $user = User::factory()->create();

    $user->fill(['is_admin' => true])->save();

    expect($user->refresh()->is_admin)->toBeFalse();
});
