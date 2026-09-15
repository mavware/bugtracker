<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * The complete role × permission matrix, against the gates the routes and
 * templates use. A failure here names the role and permission that drifted.
 */
test('each role is granted exactly the permissions of its station', function (string $role, string $permission, bool $allowed) {
    $user = User::factory()->withRoles([UserRole::from($role)])->create();

    expect(Gate::forUser($user)->allows($permission))->toBe($allowed)
        ->and($user->hasPermission(Permission::from($permission)))->toBe($allowed);
})->with([
    'homeowner cannot open the admin panel' => ['homeowner', 'access-admin', false],
    'homeowner cannot manage customers' => ['homeowner', 'manage-customers', false],
    'professional cannot open the admin panel' => ['professional', 'access-admin', false],
    'professional can manage customers' => ['professional', 'manage-customers', true],
    'admin can open the admin panel' => ['admin', 'access-admin', true],
    'admin can manage customers' => ['admin', 'manage-customers', true],
]);

test('an account holding several roles has every permission any of them grants', function () {
    $user = User::factory()->withRoles([UserRole::Homeowner, UserRole::Professional])->create();

    expect($user->hasPermission(Permission::ManageCustomers))->toBeTrue()
        ->and($user->hasPermission(Permission::AccessAdmin))->toBeFalse()
        ->and($user->isAdmin())->toBeFalse();
});

test('an account with no role has no permission at all', function () {
    $user = User::factory()->withRoles([])->create();

    expect($user->hasPermission(Permission::ManageCustomers))->toBeFalse()
        ->and($user->hasPermission(Permission::AccessAdmin))->toBeFalse();
});

test('roles are held in a fixed order however they were granted', function () {
    $user = User::factory()->create();
    $user->grantRole(UserRole::Admin);
    $user->grantRole(UserRole::Professional);
    $user->grantRole(UserRole::Professional);
    $user->save();

    expect($user->refresh()->roles->all())->toBe([UserRole::Homeowner, UserRole::Professional, UserRole::Admin]);
});

test('a guest has no permission at all', function (Permission $permission) {
    expect(Gate::allows($permission->value))->toBeFalse();
})->with(Permission::cases());

test('a new account is a homeowner', function () {
    expect(User::factory()->create()->roles->all())->toBe([UserRole::Homeowner])
        ->and(User::factory()->create()->isAdmin())->toBeFalse();
});
