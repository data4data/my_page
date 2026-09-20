import { createRouter, createWebHistory } from 'vue-router';

/**
 * The visit card's routes, and only those.
 *
 * Laravel already serves every path below to the same Blade shell
 * (routes/web.php); this decides which Vue page mounts. Nothing here imports
 * anything under pages/admin, which is the point: the public bundle cannot
 * contain the workspace's code if it never references it.
 */
const routes = [
    {
        path: '/',
        name: 'public',
        component: () => import('./pages/public/PublicPage.vue'),
    },
    {
        path: '/hi-developer',
        name: 'hi-developer',
        // Same component as '/' — the connect form renders as a popup over
        // the already-fetched public page rather than being its own page.
        meta: { connect: true },
        component: () => import('./pages/public/PublicPage.vue'),
    },
    // The same two pages under an explicit language, matching the routes
    // Laravel serves. The default language keeps the bare path, so only the
    // other locales ever reach these.
    {
        path: '/:locale(en|nl)',
        name: 'public-language',
        component: () => import('./pages/public/PublicPage.vue'),
    },
    {
        path: '/:locale(en|nl)/hi-developer',
        name: 'hi-developer-language',
        // Both connect routes are found by this flag rather than by name, so
        // the popup opens on either.
        meta: { connect: true },
        component: () => import('./pages/public/PublicPage.vue'),
    },
];

export default createRouter({
    history: createWebHistory(),
    routes,
});
