import { createApp } from 'vue';
import PrimeVue from 'primevue/config';
import App from './App.vue';
import { reportError } from './shared/api';

/**
 * The backstop under every try/catch. A render error or a promise nobody
 * awaited used to blank the page or vanish silently — the handled paths still
 * say something better, this only covers the ones that say nothing at all.
 */
const catchTheRest = (app) => {
    app.config.errorHandler = (error) => reportError(error);

    window.addEventListener('unhandledrejection', (event) => {
        reportError(event.reason);
        // Handled here, so it is not also logged as uncaught.
        event.preventDefault();
    });
};

/** The half of the bootstrap app-public.js and app-admin.js share. */
export const mountApp = (router) => {
    const app = createApp(App)
        .use(router)
        .use(PrimeVue, {
            unstyled: true,
            // Monday-first, matching the rest of the planner.
            locale: { firstDayOfWeek: 1 },
        });

    catchTheRest(app);

    return app.mount('#app');
};
