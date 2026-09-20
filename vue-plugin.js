import vue from '@vitejs/plugin-vue';

/**
 * The Vue SFC plugin, configured once: vite.config.js and vitest.config.js are
 * separate, but must compile a component the same way, or a test passes
 * against markup the app never renders.
 */
export const vuePlugin = () => vue({
    template: {
        compilerOptions: {
            // A <template> comment is markup, so Vue compiles it into a real
            // DOM node. The production build already drops them; this drops
            // them in development and in tests too. Vue's own <!--v-if-->
            // anchors are a different thing and stay.
            comments: false,
        },
    },
});
