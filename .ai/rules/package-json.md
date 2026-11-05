---
paths:
  - package.json
---

# Package Json

## vite-plus upgrades are a manual migration, not a version bump
vite-plus is pinned exactly (1.5.0 since 2026-11-05) and the root `vite` dependency is the alias `npm:@voidzero-dev/vite-plus-core@<same version>`, repeated under `overrides`; `vp build` refuses to run when the two disagree. Upgrade it with `npx --package=vite-plus@<target> vp migrate --no-interactive` and review the diff rather than editing the number: the migrator also rewrites vitest imports in tests/js and packages/*/tests when APIs move, and may add a `devEngines` block pinning npm, which this repo does not keep. vite-plus 1.x needs Node ^22.18 || ^24.11 || >=26 and npm 11+ (npm 10.9.2 crashes installing it with "Cannot read properties of null (reading 'edgesOut')"). Dependabot is told to ignore vite-plus for this reason. The Vitest 4 `test.clearMocks: false` compatibility setting the 1.0 migrator added was removed the same day because every spec passes with Vitest 5's default.
