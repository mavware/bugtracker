---
paths:
  - 'resources/js/**'
---

# Js

## JavaScript tests run through vite-plus, not a vitest dependency
The detection modules are covered by Vitest specs in tests/js/ (mirroring resources/js/ paths, *.test.js). Run them with `npm run test` or `vp test run`; watch mode is `vp test watch`. Do NOT add vitest to package.json — it ships inside vite-plus and `vp test` wraps it, so no config file is needed either. Tests use plain objects for ImageData ({width, height, data: Uint8ClampedArray}) via tests/js/helpers.js, so no canvas, jsdom or DOM environment is required; a neutral-grey pixel passes through toGrayscale unchanged, which keeps expected values readable. When changing detector.js or tracker.js thresholds, update these specs — they pin the behaviour those constants encode (dark-blob filtering, four-neighbour connectivity, background absorption of a stationary bug, noise rejection by displacement).

## Keep capture decisions in captureLogic.js — the DOM layer has no test environment
capture.js is orchestration only (DOM lookups, listeners, timers, camera/uploader wiring) and is NOT covered by tests: no jsdom or happy-dom is installed, so vitest runs in the node environment and cannot mount the page. Put any new decision — a threshold, a payload shape, a message, a geometry calculation — in captureLogic.js instead, where it can be unit tested; capture.js should read as plumbing that calls it. captureLogic.js also owns formatClock, shared with report.js. If DOM-level coverage of the start/end/discard flow is ever wanted, it needs jsdom or happy-dom added to package.json first — ask before adding it.

## capture.js is covered by DOM tests under happy-dom — supersedes the "no test environment" note
CORRECTION to the earlier note saying capture.js cannot be tested. happy-dom is now a devDependency, and tests/js/surveillance/capture.test.js mounts the page markup and drives the real module. Opt in per file with the `// @vitest-environment happy-dom` docblock on line 1 — do not set a global environment, since the pure specs are faster in node. The pattern: vi.hoisted() holds the stub fns, vi.mock() replaces camera/brightness/uploader/wakeLock (detector and tracker stay real), fake timers keep the frame loop from running until advanced, and `await vi.advanceTimersByTimeAsync(0)` settles the awaited start-up chain after a click. happy-dom has no canvas 2d context, so the overlay element needs its getContext stubbed in the fixture. captureLogic.js still holds the pure decisions and is tested in the node environment.
