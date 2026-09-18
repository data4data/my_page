import { defineConfig } from 'vitest/config';
import { vuePlugin } from './vue-plugin';

// Deliberately separate from vite.config.js: that config loads the Laravel and
// Tailwind plugins, which expect a PHP app and a real asset build. Tests only
// need Vue SFC compilation and a DOM — compiled the same way the app is, which
// is what vue-plugin.js is for.
export default defineConfig({
    plugins: [vuePlugin()],
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['resources/js/**/*.test.js'],
        restoreMocks: true,
    },
});
