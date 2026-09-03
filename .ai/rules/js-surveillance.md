---
paths:
  - 'resources/js/surveillance/**'
---

# Js Surveillance

## The capture start sequence is ordered on purpose — checklist, camera, countdown, then measure
startNight() runs: window.confirm(PREFLIGHT_MESSAGE) → camera.start() → countdownToLeave() → calibrate → reference upload → detection. The order is load-bearing, do not reshuffle it. The checklist comes before the camera opens because the light must be on before calibration measures the scene and someone who backs out should not have been filmed. The LEAVE_ROOM_SECONDS (5) countdown comes before calibration, the reference photo and the background model, so all three describe an empty room — measuring first would bake the user into the background and record them walking out as a sighting. Copy and timings live in captureLogic.js (PREFLIGHT_MESSAGE, LEAVE_ROOM_SECONDS, countdownMessage). NOTE for tests: the earlier "advanceTimersByTimeAsync(0) settles the start-up chain" advice no longer holds — starting a night now needs advanceTimersByTimeAsync(LEAVE_ROOM_SECONDS * 1000), which the startWatching() helper does; a test that stubs window.confirm false must do so AFTER starting, or the pre-flight cancels the night.
