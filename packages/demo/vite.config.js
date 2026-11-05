import { fileURLToPath } from "node:url";
import { defineConfig } from "vite-plus";

// Two plain pages, built with relative asset paths so the same output serves
// from a repository subpath on GitHub Pages or from the root of any host.
export default defineConfig({
    test: {
        // Vitest v4 compatibility: preserve mock call history.
        // Remove after tests no longer rely on calls from setup or earlier tests.
        // https://viteplus.dev/guide/vitest-v5#remove-unneeded-compatibility-settings
        // https://vitest.dev/guide/migration/#clearmocks-is-enabled-by-default
        clearMocks: false,
    },
    base: "./",
    build: {
        rollupOptions: {
            input: {
                index: fileURLToPath(new URL("./index.html", import.meta.url)),
                report: fileURLToPath(new URL("./report.html", import.meta.url)),
            },
        },
    },
});
