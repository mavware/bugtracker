---
paths:
  - 'app/Actions/Surveillance/**'
---

# Actions Surveillance

## Dismissed tracks are excluded from all analytics and report payloads
bug_tracks.dismissed_at marks a track as a user-confirmed false positive ("not a bug"). Every aggregate or payload query (session analytics, report JSON island, multi-night heatmap) must go through BugTrack's confirmed() scope; dismissed tracks appear only in the report's sightings table so they can be restored. Toggling a track re-runs ComputeSessionAnalytics for its session. The heatmap aggregates only Completed sessions and scales endpoints into the backdrop session's frame before clustering.
