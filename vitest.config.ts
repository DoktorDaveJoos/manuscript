import path from 'node:path';
import { defineConfig } from 'vitest/config';

// Standalone config (not merged with vite.config.ts) so tests never load the
// laravel/wayfinder/sentry plugins.
export default defineConfig({
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, 'resources/js'),
        },
    },
    test: {
        include: ['resources/js/**/*.test.ts'],
        environment: 'node',
    },
});
