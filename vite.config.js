import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import { vuePlugin } from './vue-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // Two bundles, so the workspace's JavaScript is never served to
            // the public page. app.blade.php picks which one to load.
            input: [
                'resources/css/app.css',
                'resources/js/app-public.js',
                'resources/js/app-admin.js',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        vuePlugin(),
    ],
    server: {
        // A name, not whatever address Vite picks: left alone it writes
        // "http://[::1]:5173" into public/hot, and CSP's host-source grammar
        // has no form for a bracketed IPv6 literal, so the bundle is blocked.
        host: 'localhost',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
