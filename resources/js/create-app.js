import { createApp } from 'vue';
import PrimeVue from 'primevue/config';
import App from './App.vue';

/**
 * The half of the bootstrap both entry points share.
 *
 * There are two entries — app-public.js and app-admin.js — so the workspace's
 * JavaScript is never served to the public page. Everything they have in
 * common lives here instead of being written twice and drifting.
 */
export const mountApp = (router) => createApp(App)
    .use(router)
    .use(PrimeVue, {
        unstyled: true,
        // Monday-first, matching the rest of the planner (Carbon's default
        // startOfWeek server-side, and the Mon–Sun week/month grids) — the
        // DatePicker otherwise opens on a Sunday-first calendar.
        locale: { firstDayOfWeek: 1 },
    })
    .mount('#app');
