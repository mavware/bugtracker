---
paths:
  - 'app/Actions/Surveillance/**'
---

# Actions Surveillance

## Dismissed tracks are excluded from all analytics and report payloads
bug_tracks.dismissed_at marks a track as a user-confirmed false positive ("not a bug"). Every aggregate or payload query (session analytics, report JSON island, multi-night heatmap) must go through BugTrack's confirmed() scope; dismissed tracks appear only in the report's sightings table so they can be restored. Toggling a track re-runs ComputeSessionAnalytics for its session. The heatmap aggregates only Completed sessions and scales endpoints into the backdrop session's frame before clustering.

## Room scoping and night grouping for surveillance aggregates
Aggregates across sessions (ComputeEntryPointHeatmap, ComputeNightlyTrend) take an optional $room and must only merge sessions recorded with the camera in one place — merging rooms makes the entry-point map meaningless. Both include finished sessions (Completed and Aborted); an aborted night's sightings are still real. A "night" is grouped by the calendar date of started_at, matching the "Night of ..." session name. Interventions are positioned on the night axis by counting how many recorded nights precede them, and a room filter also keeps interventions with a null room (they apply everywhere).
