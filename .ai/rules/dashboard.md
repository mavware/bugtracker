---
paths:
  - 'resources/views/pages/dashboard/**'
---

# Dashboard

## The dashboard section's pages live in pages/dashboard; pages/surveillance keeps only the per-night pages
Moved 2026-09-11: ⚡surveillance.blade.php (the dashboard itself, a full-page Livewire component registered with Route::livewire('dashboard', 'pages::dashboard.surveillance') in routes/web.php, named dashboard, tested with Livewire::test('pages::dashboard.surveillance')), ⚡customers, ⚡rooms and ⚡trends are resources/views/pages/dashboard/*, registered and tested as pages::dashboard.customers / .rooms / .trends. Only the per-night pages, ⚡capture and ⚡report, stay in pages/surveillance (pages::surveillance.capture / .report). The route names did not change — surveillance.customers, surveillance.rooms, surveillance.trends and their /surveillance/... URLs are what the sidebar, tests and links use — so renaming a view here means touching routes/surveillance.php or routes/web.php and the matching Livewire::test() calls, not the route names. Run php artisan view:clear and route:clear after moving a page; the cached route file otherwise still points at the old view name.
