---
paths:
  - 'packages/surveillance/**'
---

# Packages Surveillance

## The library is published as @mavware/bug-surveillance; its types are hand-written and must be edited alongside signatures
packages/surveillance is published to npm as @mavware/bug-surveillance (public, MIT, provenance on). src/index.js is the only entry: anything not re-exported there is private by construction, and adding an export is a public API change. src/index.d.ts is HAND-WRITTEN, not generated — JSDoc coverage is too thin for tsc --declaration to produce anything but `any`. It is not derived from the source, so nothing detects drift automatically: when you change a signature in src, change index.d.ts in the same commit. `npm run typecheck --workspace packages/surveillance` compiles the declarations together with tests/types.test-d.ts, a compile-only exercise of every export; that catches an inconsistent or unusable declaration but cannot prove it matches runtime behaviour. The typecheck runs typescript through `npx --yes -p typescript@5` deliberately, so no TypeScript dependency is added to the repo. tests/types.test-d.ts is invisible to Vitest because its name ends `.test-d.ts`, which the runner's `*.test.js` pattern does not match. `files: ["src"]` ships the JS and the declarations; README and LICENSE ride along automatically. Releases go through .github/workflows/release.yml on a `v*` tag, which refuses to publish when the tag and package version disagree and needs an NPM_TOKEN secret.
