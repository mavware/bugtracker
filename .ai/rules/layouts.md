---
paths:
  - 'resources/views/layouts/**'
---

# Layouts

## data-app-nav marks the chrome capture.js locks — do not drop it or rename it to data-nav
The sidebar layout marks two regions with a valueless data-app-nav attribute: <flux:sidebar> and the mobile <flux:header>. capture.js sets `inert` on every [data-app-nav] while a night records, because leaving the capture page ends the night and a stray tap on a sidebar link would discard hours of watching with nothing asking first. inert is used rather than pointer-events so the links leave the tab order too; an opacity-40 class is toggled alongside it so the lock does not read as a broken page. Do NOT name this data-nav — Flux's own markup already uses data-nav-sidebar, data-nav-footer and data-navmenu-icon, and the near-collision makes string searches useless. CapturePageTest counts the marked ELEMENTS with a regex, not occurrences of the string: a valueless Blade attribute renders as data-app-nav="data-app-nav", so the raw text appears twice per tag.
