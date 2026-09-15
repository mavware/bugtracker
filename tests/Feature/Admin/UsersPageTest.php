<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('the list can be searched by name or email', function () {
    $admin = User::factory()->admin()->create(['name' => 'Site Admin', 'email' => 'admin@example.com']);
    User::factory()->create(['name' => 'Dana Alvarez', 'email' => 'dana@example.com']);
    User::factory()->create(['name' => 'Rob Brody', 'email' => 'rob@example.com']);

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->set('search', 'alvarez')
        ->assertSee('dana@example.com')
        ->assertDontSee('rob@example.com');
});

test('the page heading and subheading are rendered by the app layout', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.users'))
        ->assertSeeInOrder([
            'Users',
            'Every account on the site.',
            '<section wire:snapshot=',
        ], false);
});

test('an admin can change another account\'s role', function (string $role) {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('setRole', $member->id, $role);

    expect($member->refresh()->role)->toBe(UserRole::from($role));
})->with(['admin', 'professional', 'homeowner']);

test('an unknown role is rejected', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('setRole', $member->id, 'superuser')
        ->assertStatus(422);

    expect($member->refresh()->role)->toBe(UserRole::Homeowner);
});

test('an admin cannot change their own role', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('setRole', $admin->id, UserRole::Homeowner->value)
        ->assertForbidden();

    expect($admin->refresh()->role)->toBe(UserRole::Admin);
});

test('every other account gets a role picker and the admin\'s own row a badge', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->professional()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->assertSeeHtml('data-test="own-role"')
        ->assertSeeHtml('data-test="role-select"');
});

test('an admin cannot delete their own account from here', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('deleteUser', $admin->id)
        ->assertForbidden();

    expect(User::find($admin->id))->not->toBeNull();
});

test('deleting a user removes their sessions, customers and stored frames', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();
    $customer = Customer::factory()->for($member)->create();
    $session = SurveillanceSession::factory()->for($member)->completed()->create(['customer_id' => $customer->id]);
    Storage::disk('local')->put("surveillance/$session->id/reference.jpg", 'jpeg');

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('deleteUser', $member->id);

    expect(User::find($member->id))->toBeNull()
        ->and(SurveillanceSession::find($session->id))->toBeNull()
        ->and(Customer::find($customer->id))->toBeNull();
    Storage::disk('local')->assertMissing("surveillance/$session->id/reference.jpg");
});

test('another account\'s sessions are untouched when one user is deleted', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();
    $bystander = User::factory()->create();
    SurveillanceSession::factory()->for($member)->completed()->create();
    $keep = SurveillanceSession::factory()->for($bystander)->completed()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('deleteUser', $member->id);

    expect(SurveillanceSession::find($keep->id))->not->toBeNull();
});
