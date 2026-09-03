---
paths:
  - 'tests/**'
---

# Tests

## Feature tests fake the local disk globally — never let one delete real recordings
tests/Pest.php binds ->beforeEach(fn () => Storage::fake('local')) to the whole Feature suite. Do not remove it. Deleting a SurveillanceSession fires a model hook that removes storage/app/private/surveillance/{id}, and the :memory: test database numbers sessions from 1, so any test that deletes a session without a faked disk deletes the developer's real session 1 off disk. This actually happened: a pagination test with no Storage::fake wiped a real reference photo, leaving the row pointing at a missing file. Per-test Storage::fake('local') calls are still fine and are kept where a test asserts on stored files.
