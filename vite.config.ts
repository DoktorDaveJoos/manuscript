import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import { sentryVitePlugin } from '@sentry/vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig(({ command }) => ({
    build: {
        sourcemap: !!process.env.SENTRY_AUTH_TOKEN,
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react({
            babel: {
                // React Compiler does whole-program AST analysis on every
                // transform. In Vite dev that compounds with HMR and balloons
                // the Node process to multi-GB over long sessions. Keep it
                // production-only — dev still gets fast, prod still gets the
                // memoization wins.
                plugins:
                    command === 'build' ? ['babel-plugin-react-compiler'] : [],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        sentryVitePlugin({
            org: process.env.SENTRY_ORG,
            project: process.env.SENTRY_PROJECT,
            authToken: process.env.SENTRY_AUTH_TOKEN,
            release: {
                name: process.env.SENTRY_RELEASE,
            },
            sourcemaps: {
                filesToDeleteAfterUpload: ['public/build/assets/*.map'],
            },
            disable: !process.env.SENTRY_AUTH_TOKEN,
        }),
    ],
    esbuild: {
        jsx: 'automatic',
    },
}));
