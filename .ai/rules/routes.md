---
paths:
  - 'routes/**'
---

# Routes

## A cached route file hides new routes from the test suite
bootstrap/cache/routes-v7.php is loaded by the test process too, so after adding or renaming a route the suite fails with "Route [name] not defined" or asserts against the old behaviour even though routes/*.php is correct. This happened on 2026-09-11 when adding the `welcome` route. Run `php artisan route:clear` (or check that file is absent) before trusting a route-related test failure.

Also: `/` (`home`) carries the `guest` middleware, so signed-in users are redirected to `dashboard` (RedirectIfAuthenticated picks the `dashboard` route by default). The welcome page itself stays reachable for them at `/welcome` (`welcome`), served by the same HomeController. Tests that need a signed-in user to see the welcome page must hit route('welcome'), not route('home').
