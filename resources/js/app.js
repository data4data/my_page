import { createApp } from 'vue';
import PrimeVue from 'primevue/config';
import App from './App.vue';
import router from './router';

createApp(App)
    .use(router)
    .use(PrimeVue, {
        unstyled: true,
        // Monday-first, matching the rest of the planner (Carbon's default
        // startOfWeek server-side, and the Mon–Sun week/month grids) — the
        // DatePicker otherwise opens on a Sunday-first calendar.
        locale: { firstDayOfWeek: 1 },
    })
    .mount('#app');
