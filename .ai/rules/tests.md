---
paths:
  - 'tests/**'
---

# Tests

## Feature tests fake the local disk globally — never let one delete real recordings
tests/Pest.php binds ->beforeEach(fn () => Storage::fake('local')) to the whole Feature suite. Do not remove it. Deleting a SurveillanceSession fires a model hook that removes storage/app/private/surveillance/{id}, and the :memory: test database numbers sessions from 1, so any test that deletes a session without a faked disk deletes the developer's real session 1 off disk. This actually happened: a pagination test with no Storage::fake wiped a real reference photo, leaving the row pointing at a missing file. Per-test Storage::fake('local') calls are still fine and are kept where a test asserts on stored files.

## A cached config makes the test suite wipe the local database
If bootstrap/cache/config.php exists, Laravel ignores the <env> overrides in phpunit.xml, so tests run with APP_ENV=local, the database session driver and the real database/database.sqlite file instead of :memory:. RefreshDatabase then runs migrate:fresh against the developer's own database and wipes it, and form posts fail with 419 because CSRF is only skipped in the testing environment. This happened on 2026-09-11. Before running the suite, check that bootstrap/cache/config.php is absent, and run `php artisan config:clear` if it is not. Do not "fix" 419s in tests by touching CSRF middleware.
