<?php

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

test('an admin can grant and revoke admin access', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();

    $component = Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('toggleAdmin', $member->id);

    expect($member->refresh()->is_admin)->toBeTrue();

    $component->call('toggleAdmin', $member->id);

    expect($member->refresh()->is_admin)->toBeFalse();
});

test('an admin cannot revoke their own access', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('toggleAdmin', $admin->id)
        ->assertForbidden();

    expect($admin->refresh()->is_admin)->toBeTrue();
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
    Storage::disk('local')->put("surveillance/{$session->id}/reference.jpg", 'jpeg');

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('deleteUser', $member->id);

    expect(User::find($member->id))->toBeNull()
        ->and(SurveillanceSession::find($session->id))->toBeNull()
        ->and(Customer::find($customer->id))->toBeNull();
    Storage::disk('local')->assertMissing("surveillance/{$session->id}/reference.jpg");
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
