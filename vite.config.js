import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import { vuePlugin } from './vue-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // Two bundles, not one. The workspace's JavaScript is never
            // served to the public page, so nobody can read the shape of the
            // private API out of a chunk fetched from the public site.
            // app.blade.php picks which pair to load.
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
        // A name, not whatever address Vite picks. Left to itself it binds to
        // IPv6 loopback and writes "http://[::1]:5173" into public/hot, which
        // every asset URL and the Content-Security-Policy are then built from.
        // CSP's host-source grammar has no form for a bracketed IPv6 literal,
        // so the browser discards that source and blocks the dev bundle.
        host: 'localhost',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
