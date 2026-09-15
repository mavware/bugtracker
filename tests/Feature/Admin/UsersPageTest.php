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

test('an admin can grant another account a role it lacks', function (string $role) {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('toggleRole', $member->id, $role);

    expect($member->refresh()->roles->all())->toBe([UserRole::Homeowner, UserRole::from($role)]);
})->with(['admin', 'professional']);

test('toggling a role the account holds takes it away and leaves the others', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->withRoles([UserRole::Homeowner, UserRole::Professional])->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('toggleRole', $member->id, 'professional');

    expect($member->refresh()->roles->all())->toBe([UserRole::Homeowner]);
});

test('an account may be left with no role at all', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('toggleRole', $member->id, 'homeowner')
        ->assertOk();

    expect($member->refresh()->roles)->toBeEmpty();
});

test('an unknown role is rejected', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('toggleRole', $member->id, 'superuser')
        ->assertStatus(422);

    expect($member->refresh()->roles->all())->toBe([UserRole::Homeowner]);
});

test('an admin cannot take the admin role away from themselves', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('toggleRole', $admin->id, UserRole::Admin->value)
        ->assertForbidden();

    expect($admin->refresh()->isAdmin())->toBeTrue();
});

test('an admin can change their own other roles', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('toggleRole', $admin->id, UserRole::Professional->value)
        ->assertOk();

    expect($admin->refresh()->roles->all())->toBe([UserRole::Professional, UserRole::Admin]);
});

test('every account gets a toggle per role, with only the admin\'s own Admin box locked', function () {
    $admin = User::factory()->admin()->create();

    $html = Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->assertSeeHtml('data-test="role-toggle-homeowner"')
        ->assertSeeHtml('data-test="role-toggle-professional"')
        ->assertSeeHtml('data-test="role-toggle-admin"')
        ->html();

    preg_match_all('/<ui-checkbox[^>]*data-test="role-toggle-(\w+)"[^>]*>/', $html, $boxes, PREG_SET_ORDER);
    $locked = collect($boxes)->filter(fn (array $box) => str_contains($box[0], 'disabled'))->map(fn (array $box) => $box[1])->values()->all();

    expect($locked)->toBe(['admin']);
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

test('an admin can edit another account\'s name and email', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['name' => 'Dana Alvarez', 'email' => 'dana@example.com']);

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->assertSeeHtml('data-test="edit-user-button"')
        ->call('edit', $member->id)
        ->assertSet('showEditor', true)
        ->assertSet('name', 'Dana Alvarez')
        ->assertSet('email', 'dana@example.com')
        ->set('name', '  Dana Alvarez-Reyes ')
        ->set('email', 'dana.reyes@example.com')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showEditor', false)
        ->assertSet('editingId', null);

    expect($member->refresh()->name)->toBe('Dana Alvarez-Reyes')
        ->and($member->email)->toBe('dana.reyes@example.com')
        ->and($member->roles->all())->toBe([UserRole::Homeowner]);
});

test('an admin can edit their own name from the users page', function () {
    $admin = User::factory()->admin()->create(['name' => 'Site Admin']);

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('edit', $admin->id)
        ->set('name', 'Site Administrator')
        ->call('save')
        ->assertHasNoErrors();

    expect($admin->refresh()->name)->toBe('Site Administrator');
});

test('an edited email must be valid and not already taken by another account', function (string $email, string $error) {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'taken@example.com']);
    $member = User::factory()->create(['email' => 'dana@example.com']);

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('edit', $member->id)
        ->set('email', $email)
        ->call('save')
        ->assertHasErrors(['email' => $error]);

    expect($member->refresh()->email)->toBe('dana@example.com');
})->with([
    'already taken' => ['taken@example.com', 'unique'],
    'not an address' => ['not-an-email', 'email'],
    'blank' => ['', 'required'],
]);

test('keeping the account\'s own email is not a uniqueness clash', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['name' => 'Dana', 'email' => 'dana@example.com']);

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('edit', $member->id)
        ->set('name', 'Dana A.')
        ->call('save')
        ->assertHasNoErrors();

    expect($member->refresh()->name)->toBe('Dana A.')
        ->and($member->email)->toBe('dana@example.com');
});

test('cancelling the editor drops the typed changes', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['name' => 'Dana Alvarez']);

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('edit', $member->id)
        ->set('name', 'Someone else')
        ->set('showEditor', false)
        ->assertSet('editingId', null)
        ->assertSet('name', '');

    expect($member->refresh()->name)->toBe('Dana Alvarez');
});

test('the editor opens with the account\'s roles ticked and saves the new set', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('edit', $member->id)
        ->assertSet('roles', ['homeowner'])
        ->set('roles', ['admin', 'professional'])
        ->call('save')
        ->assertHasNoErrors();

    expect($member->refresh()->roles->all())->toBe([UserRole::Professional, UserRole::Admin]);
});

test('the editor rejects a role that does not exist', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('edit', $member->id)
        ->set('roles', ['superuser'])
        ->call('save')
        ->assertHasErrors(['roles.0']);

    expect($member->refresh()->roles->all())->toBe([UserRole::Homeowner]);
});

test('the editor keeps an admin\'s own admin role whatever is submitted, but saves their other roles', function () {
    $admin = User::factory()->admin()->create(['name' => 'Site Admin']);

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('edit', $admin->id)
        ->assertSeeHtml('data-test="own-roles-note"')
        ->set('roles', ['homeowner'])
        ->set('name', 'Still the admin')
        ->call('save')
        ->assertHasNoErrors();

    expect($admin->refresh()->roles->all())->toBe([UserRole::Homeowner, UserRole::Admin])
        ->and($admin->name)->toBe('Still the admin');
});
