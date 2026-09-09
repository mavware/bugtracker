---
paths:
  - 'resources/views/**'
---

# Views

## Hiding a Flux button by class needs the app.css override
Flux buttons ship with `inline-flex`, and Tailwind v4 emits `.hidden` earlier in the utilities layer than `.inline-flex`, so `<flux:button class="hidden">` alone loses the cascade and the button stays on screen. This kept End night and Discard night visible on the capture page before any night had started, and the same latent bug sat on the dashboard's "Remove local copies" button. resources/css/app.css now carries an unlayered `[data-flux-button].hidden { display: none }` to settle it — keep that rule, and do not "fix" a stuck button by switching the JS from classList to something else. Scripts (capture.js, claim.js) toggle plain `hidden` on these buttons and rely on it.
