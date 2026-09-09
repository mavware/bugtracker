import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

// Two plain pages, built with relative asset paths so the same output serves
// from a repository subpath on GitHub Pages or from the root of any host.
export default defineConfig({
    base: './',
    build: {
        rollupOptions: {
            input: {
                index: fileURLToPath(new URL('./index.html', import.meta.url)),
                report: fileURLToPath(new URL('./report.html', import.meta.url)),
            },
        },
    },
});
