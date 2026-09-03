---
paths:
  - 'tests/js/**'
---

# Tests Js

## capture.test.js must strip window listeners between tests — they leak across boots
bootCaptureApp() re-imports capture.js for every test, and each import registers fresh window listeners (beforeunload, pagehide), but happy-dom's window lives for the whole file. Without cleanup they accumulate and a PREVIOUS test's app instance — whose closure still believes running === true — answers events dispatched by the current test. This produced both false failures (a beforeunload guard appearing to fire before any night started) and false passes (the pagehide flush assertion satisfied by a leaked listener calling the same shared stub). bootCaptureApp spies on window.addEventListener to record every registration into windowListeners, and afterEach calls removeTrackedWindowListeners(). Keep that in place, and add any new window listener to the same path rather than attaching one directly.
