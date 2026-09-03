---
paths:
  - 'app/Actions/Surveillance/**'
---

# Actions Surveillance

## Dismissed tracks are excluded from all analytics and report payloads
bug_tracks.dismissed_at marks a track as a user-confirmed false positive ("not a bug"). Every aggregate or payload query (session analytics, report JSON island, multi-night heatmap) must go through BugTrack's confirmed() scope; dismissed tracks appear only in the report's sightings table so they can be restored. Toggling a track re-runs ComputeSessionAnalytics for its session. The heatmap aggregates only Completed sessions and scales endpoints into the backdrop session's frame before clustering.

## Room scoping and night grouping for surveillance aggregates
Aggregates across sessions (ComputeEntryPointHeatmap, ComputeNightlyTrend) take an optional $room and must only merge sessions recorded with the camera in one place — merging rooms makes the entry-point map meaningless. Both include finished sessions (Completed and Aborted); an aborted night's sightings are still real. A "night" is grouped by the calendar date of started_at, matching the "Night of ..." session name. Interventions are positioned on the night axis by counting how many recorded nights precede them, and a room filter also keeps interventions with a null room (they apply everywhere).

## Customer is the outer grouping for surveillance aggregates
Sessions and interventions carry a nullable customer_id (Customer belongs to the user; a homeowner leaves it null). Aggregates take ($user, ?string $room, ?int $customerId) — customer is the outer scope, room the inner one, since rooms belong to a property. A customer filter shows ONLY that customer's interventions, unlike a room filter which also keeps null-room ones: sealing a front door plausibly affects every room of one home, but baiting one property says nothing about another. customer_id is nullOnDelete, never cascade — a DB cascade would delete sessions without firing SurveillanceSession's deleting hook and orphan their stored frames on disk; removing a customer un-groups their nights instead.

## Room label edits go through RoomLabels for both users and admins
Rooms have no table — they are a string on sessions — so renaming one rewrites every session carrying it. RoomLabels::groups(?User) is the single implementation behind both the user page (pages::surveillance.rooms, scoped to Auth::user()) and the admin page (pages::admin.rooms, all accounts); do not duplicate the grouping or update query. Groups are keyed by sha1(user|customer|room) and returned as RoomLabel value objects (not array shapes — PHPStan generics are invariant and reject the shape). Authorisation is by construction: a page looks a key up only within the groups it can already see, so another account's key is simply not found and 404s. Renaming onto an existing label deliberately merges the two.
