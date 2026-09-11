---
paths:
  - 'resources/views/pages/surveillance/**'
---

# Pages Surveillance

## A night's report page is also its live view; the capture page is for Pending nights only
pages/surveillance/report is the one link for a night in any state: Pending shows a "not started" callout pointing at the capture page, Active shows a wire:poll.30s live block (sightings so far, last seen, last check-in, via App\Actions\Surveillance\DescribeNightInProgress, which the dashboard tonight panel shares) and reloads into the report once the device ends the night, Completed/Aborted is the report. The capture page mount() redirects anything but Pending to the report: opening capture for an Active night and pressing Start re-uploads the reference frame and resets started_at, mis-timing every sighting already stored, so never link an Active night to surveillance.capture (tonight panel and sessions list link to the report). endStuckNight on the report ends a night server-side at last_heartbeat_at, but only once the heartbeat is stale (DescribeNightInProgress::STALE_HEARTBEAT_MINUTES) — while the device is checking in it owns the night and ending it would 409 its uploads. This is a user action; the standing rule against a scheduled job that ends overdue nights still holds.
