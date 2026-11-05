---
paths:
  - 'resources/views/pages/**'
---

# Pages

## A night is discarded from its report, never from the capture page
The capture header carries Check camera / Start tracking / End night only. Discarding (status Aborted, which keeps the night and its report but drops it from trends and entry points) is a report-page action, in both flavours: `toggleDiscarded` on pages/surveillance/report for an account night, and the `data-report="discard"` button driven by localReport.js for a browser-kept one. Both toggle back, so it is undoable. capture.js always ends a night with `aborted: false`; the end and import endpoints still accept the flag, so do not strip it server-side. Do not put a Discard button back on the capture page — the call is about whether the setup was any good, which only the finished trails show.

## No static first-class callables on imported classes inside ⚡ single-file components (PHP 8.6)
Livewire compiles a ⚡ single-file component into an anonymous class, and PHP 8.6.0RC3 fails to resolve `use` imports for a static first-class callable inside an anonymous class: `array_map(UserRole::from(...), $roles)` dies with `Class "UserRole" not found` even though the import is present (a plain closure and a named class both work; reproduced outside Livewire on 2026-11-05 with the Herd php86 build). Write `fn (string $role): UserRole => UserRole::from($role)` instead, or use the fully qualified name. pages/admin/⚡users.blade.php was fixed this way when the project moved to PHP 8.6. Re-test the FCC form once 8.6 GA ships; this may be an RC bug.
