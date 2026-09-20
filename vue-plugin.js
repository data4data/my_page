import vue from '@vitejs/plugin-vue';

/**
 * The Vue SFC plugin, configured once.
 *
 * vite.config.js and vitest.config.js are deliberately separate — the test
 * config loads neither Laravel nor Tailwind — but they must compile a
 * component the same way, or a test passes against markup the app never
 * renders. This is the one place that says how.
 */
export const vuePlugin = () => vue({
    template: {
        compilerOptions: {
            // A comment in a <template> is markup, so Vue compiles it into a
            // real DOM node: visible to anyone who opens the inspector, and
            // served in the page for a crawler to read. The production build
            // already drops them; this drops them in development and in tests
            // too, so an explanation can stay next to the markup it explains
            // without ever reaching the page.
            //
            // Vue's own <!--v-if--> anchors are a different thing — they mark
            // the place an absent branch would go — and they stay.
            comments: false,
        },
    },
});
