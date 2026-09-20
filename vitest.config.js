import { defineConfig } from 'vitest/config';
import { vuePlugin } from './vue-plugin';

// Separate from vite.config.js, which loads the Laravel and Tailwind plugins:
// tests need only Vue SFC compilation and a DOM, compiled the same way.
export default defineConfig({
    plugins: [vuePlugin()],
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['resources/tests/**/*.test.js'],
        // The dictionary halves the entry points would have registered.
        setupFiles: ['resources/tests/setup.js'],
        restoreMocks: true,
    },
});
