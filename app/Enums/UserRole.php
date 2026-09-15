<?php

namespace App\Enums;

/**
 * What an account is for. An account holds any number of roles and may do
 * whatever any of them grants; what a role may do is derived here, so adding a
 * permission never touches the database.
 */
enum UserRole: string
{
    /** Watches their own home. The default for a new account. */
    case Homeowner = 'homeowner';

    /** A pest technician watching properties on customers' behalf. */
    case Professional = 'professional';

    /** Runs the site. Also has everything a professional has, on its own. */
    case Admin = 'admin';

    /**
     * The roles an account may pick for itself at registration. Admin is
     * granted only by another admin or the user:promote command.
     *
     * @return list<self>
     */
    public static function selfAssignable(): array
    {
        return [self::Homeowner, self::Professional];
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Homeowner => [],
            self::Professional => [Permission::ManageCustomers],
            self::Admin => [Permission::ManageCustomers, Permission::AccessAdmin],
        };
    }

    public function grants(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Homeowner => __('Homeowner'),
            self::Professional => __('Professional'),
            self::Admin => __('Admin'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Homeowner => __('Watching my own home.'),
            self::Professional => __('Watching properties for customers.'),
            self::Admin => __('Runs the site.'),
        };
    }
}
