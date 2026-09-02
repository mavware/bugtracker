---
paths:
  - 'app/Http/Controllers/Surveillance/**'
---

# Surveillance

## Surveillance ingestion: closed tracks only, idempotent inserts
The capture page's JS does all computer vision; the server only accepts closed tracks (never appends). Each track POST is an idempotent insert keyed by (surveillance_session_id, client_track_id) — retries and duplicates are safe by design, so never add append/merge semantics. Points are a JSON array on bug_tracks capped at 5000/track; verification crops ride inline as base64 JPEG (≤20KB decoded, magic-byte checked). Reference photos and crops live on the private local disk under surveillance/{session_id}/ and are served only via policy-checked ImageController routes — never the public disk.
