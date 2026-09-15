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
    $user = User::factory()->create(['role' => UserRole::from($role)]);

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

test('a guest has no permission at all', function (Permission $permission) {
    expect(Gate::allows($permission->value))->toBeFalse();
})->with(Permission::cases());

test('a new account is a homeowner', function () {
    expect(User::factory()->create()->role)->toBe(UserRole::Homeowner)
        ->and(User::factory()->create()->isAdmin())->toBeFalse();
});
