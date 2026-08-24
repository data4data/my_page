import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

// Deliberately separate from vite.config.js: that config loads the Laravel and
// Tailwind plugins, which expect a PHP app and a real asset build. Tests only
// need Vue SFC compilation and a DOM.
export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['resources/js/**/*.test.js'],
        restoreMocks: true,
    },
});
