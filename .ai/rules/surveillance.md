---
paths:
  - 'app/Http/Controllers/Surveillance/**'
---

# Surveillance

## Surveillance ingestion: closed tracks only, idempotent inserts
The capture page's JS does all computer vision; the server only accepts closed tracks (never appends). Each track POST is an idempotent insert keyed by (surveillance_session_id, client_track_id) — retries and duplicates are safe by design, so never add append/merge semantics. Points are a JSON array on bug_tracks capped at 5000/track; verification crops ride inline as base64 JPEG (≤20KB decoded, magic-byte checked). Reference photos and crops live on the private local disk under surveillance/{session_id}/ and are served only via policy-checked ImageController routes — never the public disk.

## Session image responses are no-store — the URLs are id-keyed, not content-keyed
ImageController serves the reference frame and crops with 'Cache-Control: private, no-store' (ImageController::CACHE_HEADERS). Do not put a max-age back on these. The routes are /surveillance/{session}/reference-image and /surveillance/{session}/tracks/{track}/crop/{position} — keyed by id, so the same URL serves a different photo once a session is deleted and the id is handed out again (a reset dev database restarts at 1). With the old 'private, max-age=86400' the browser reused the previous session's photo for 24 hours without revalidating, which read as "deleting the session did not delete the picture". no-store also keeps photographs of the inside of a home out of the browser's on-disk cache, matching the owner-only policy.
