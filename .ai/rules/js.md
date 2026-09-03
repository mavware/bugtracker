---
paths:
  - 'resources/js/**'
---

# Js

## JavaScript tests run through vite-plus, not a vitest dependency
The detection modules are covered by Vitest specs in tests/js/ (mirroring resources/js/ paths, *.test.js). Run them with `npm run test` or `vp test run`; watch mode is `vp test watch`. Do NOT add vitest to package.json — it ships inside vite-plus and `vp test` wraps it, so no config file is needed either. Tests use plain objects for ImageData ({width, height, data: Uint8ClampedArray}) via tests/js/helpers.js, so no canvas, jsdom or DOM environment is required; a neutral-grey pixel passes through toGrayscale unchanged, which keeps expected values readable. When changing detector.js or tracker.js thresholds, update these specs — they pin the behaviour those constants encode (dark-blob filtering, four-neighbour connectivity, background absorption of a stationary bug, noise rejection by displacement).
