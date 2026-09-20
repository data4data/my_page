import { createApp } from 'vue';
import PrimeVue from 'primevue/config';
import App from './App.vue';

/** The half of the bootstrap app-public.js and app-admin.js share. */
export const mountApp = (router) => createApp(App)
    .use(router)
    .use(PrimeVue, {
        unstyled: true,
        // Monday-first, matching the rest of the planner.
        locale: { firstDayOfWeek: 1 },
    })
    .mount('#app');
