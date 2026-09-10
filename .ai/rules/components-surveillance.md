---
paths:
  - 'resources/views/components/surveillance/**'
---

# Components Surveillance

## The capture panel's side column has two faces — setup-help goes on start, night-help arrives
capture-panel renders two blocks in its side column: data-capture="setup-help" (the setupHelp slot: aiming advice, or the welcome/watch hero copy) which capture.js hides the moment a night starts, and data-capture="night-help" (the nightHelp slot plus the built-in "Keep it running all night" / "If the screen keeps sleeping" box) which starts hidden and is revealed at that same moment. The screen-sleep advice lives in night-help on purpose: a slept screen silently ends the night, so it has to be on screen while the night runs, not only during setup. Do not move it back into setup-help. Both elements are required by capture.js and its happy-dom fixture. The welcome page and /watch share the panel through watch-panel with asideFirst, so the copy column precedes the camera and the camera keeps lg:col-span-2; the welcome page has no mock card of its own — the sample room is drawn only inside the panel's placeholder. A page may add one extra start button as data-capture="start-alias"; capture.js forwards its click to the real start button, so never wire a second startNight() call.
