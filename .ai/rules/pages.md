---
paths:
  - 'resources/views/pages/**'
---

# Pages

## A night is discarded from its report, never from the capture page
The capture header carries Check camera / Start watching / End night only. Discarding (status Aborted, which keeps the night and its report but drops it from trends and entry points) is a report-page action, in both flavours: `toggleDiscarded` on pages/surveillance/report for an account night, and the `data-report="discard"` button driven by localReport.js for a browser-kept one. Both toggle back, so it is undoable. capture.js always ends a night with `aborted: false`; the end and import endpoints still accept the flag, so do not strip it server-side. Do not put a Discard button back on the capture page — the call is about whether the setup was any good, which only the finished trails show.
