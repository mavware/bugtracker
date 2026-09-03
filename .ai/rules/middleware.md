---
paths:
  - 'app/Http/Middleware/**'
---

# Middleware

## Site admin access, and the privacy line it does not cross
users.is_admin gates the /admin panel via the 'admin' middleware alias (EnsureUserIsAdmin, registered in bootstrap/app.php). It is deliberately NOT in User's #[Fillable], so no request payload can promote an account; grant it with `php artisan user:promote {email}` or from the admin users page. Admins cannot change their own admin flag or delete their own account from the panel, which prevents lockout. The panel manages accounts and metadata only: SurveillanceSessionPolicy stays owner-only and must not gain an admin bypass or a Gate::before, because reference frames and crops are photographs of the inside of someone's home. Deleting a user must go through Actions\Admin\DeleteUserAccount, which removes sessions via Eloquent so the storage-cleanup hook fires.
