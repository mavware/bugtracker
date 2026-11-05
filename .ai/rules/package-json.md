---
paths:
  - package.json
---

# Package Json

## vite-plus upgrades are a manual migration, not a version bump
vite-plus is pinned exactly (0.3.0). From 0.3.1 on, `vp build` refuses to run unless the root `vite` dependency is the alias `npm:@voidzero-dev/vite-plus-core@<same version>` (plus an `overrides` entry), and `vp migrate` also rewrites every `from 'vitest'` import in tests/js and packages/*/tests and adds a devEngines block pinning npm. vite-plus 1.x (Vitest 5, fixes the @vitest/mocker audit advisories) additionally needs Node ^22.18 || ^24.11 || >=26 and an npm whose arborist does not crash on its alias deps: npm 10.9.2 (bundled with Node 23.5) fails with "Cannot read properties of null (reading 'edgesOut')", npm 12 installs it fine. Upgrade Node/npm first, then run `npx --package=vite-plus@<target> vp migrate --no-interactive` and review the diff; do not bump the number alone. Dependabot is told to ignore vite-plus for this reason.
