---
paths:
  - 'app/Enums/**'
---

# Enums

## Roles and permissions: users.role drives every role-based gate — supersedes the is_admin / EnsureUserIsAdmin note
CORRECTION to the middleware rule: users.is_admin and app/Http/Middleware/EnsureUserIsAdmin (the 'admin' alias) are gone since 2026-09-15. An account has one users.role (UserRole enum: homeowner default, professional, admin). What a role may do is derived in UserRole::permissions(), never stored: Permission::AccessAdmin (the /admin panel) and Permission::ManageCustomers (the customers page; professional AND admin). AppServiceProvider registers one Gate per Permission case, so routes use `can:access-admin` / `can:manage-customers` and templates `@can(Permission::X->value)`; add a new permission by adding a case and mapping it, not by adding middleware. role is deliberately not #[Fillable] (User sets a default of homeowner in $attributes); it changes only via the admin users page (setRole, an admin cannot change their own), `php artisan user:promote {email} [--role=admin|professional] [--demote]`, or registration, which accepts only UserRole::selfAssignable() (never admin). Downstream customer pickers (⚡sessions, ⚡trends, ⚡night-details, claim-nights) are still keyed on whether the user has customers, not on the role, so a demoted professional keeps seeing their existing groupings. Do not add a Gate::before for admins: SurveillanceSessionPolicy stays owner-only because the frames are photos of someone's home.
