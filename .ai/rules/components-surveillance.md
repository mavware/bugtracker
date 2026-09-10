---
paths:
  - 'resources/views/components/surveillance/**'
---

# Components Surveillance

## The capture panel's side column has two faces — setup-help goes on start, night-help arrives
capture-panel renders two blocks in its side column: data-capture="setup-help" (the setupHelp slot: aiming advice, or the welcome/watch hero copy) which capture.js hides the moment a night starts, and data-capture="night-help" (the nightHelp slot plus the built-in "Keep it running all night" / "If the screen keeps sleeping" box) which starts hidden and is revealed at that same moment. The screen-sleep advice lives in night-help on purpose: a slept screen silently ends the night, so it has to be on screen while the night runs, not only during setup. Do not move it back into setup-help. Both elements are required by capture.js and its happy-dom fixture. The welcome page and /watch share the panel through watch-panel with asideFirst, so the copy column precedes the camera and the camera keeps lg:col-span-2; the welcome page has no mock card of its own — the sample room is drawn only inside the panel's placeholder. A page may add one extra start button as data-capture="start-alias"; capture.js forwards its click to the real start button, so never wire a second startNight() call.

## setup-help gives way the moment the checklist is confirmed, not when recording begins — and comes back if the start fails
CORRECTION to "The capture panel's side column has two faces": capture.js showNightReading(true) runs right after the room checklist is confirmed, before the camera opens, so the hero copy / setup advice is gone for the countdown and calibration too (the status has left Idle). showNightReading(false) runs on every path that leaves the page idle again: a too-dark calibration and the start button's catch handler (refused camera, failed reference upload). A camera check and a cancelled checklist leave the setup reading where it is. Do not move the swap back to the moment recording begins — the user asked for it to follow the status leaving Idle.

## setup-help follows the status: gone whenever it leaves Idle, including a camera check (supersedes the "camera check leaves it" note)
CORRECTION: capture.js showNightReading(true) now also runs when a camera check opens, and showNightReading(false) when the check is closed or refused. So the rule is simply: the setup reading (hero copy / aiming advice) is on screen only while the status is Idle (or the checklist is open over an idle page); any status that is not Idle shows the night-help column instead, and every path back to Idle or Error restores setup-help. Specs in capture.test.js pin the check open/close, refused check, cancelled checklist, and start-from-open-check cases.
