import { createRouter, createWebHistory } from 'vue-router';
import { adminUrl } from './shared/admin-path';

// Independent, lazily-loaded pages. Laravel's own routes (routes/web.php)
// already serve every path below to the same Blade shell — this router just
// decides which Vue page mounts for each, so e.g. the admin editor's JS
// never ships to public visitors.
//
// The admin editor lives behind a per-install prefix (ADMIN_PATH, see
// config/admin.php) rather than /admin, and is never linked from the public
// page — see PublicPage.vue. Its paths are built from adminUrl() so this file
// and routes/web.php can't disagree about what that prefix is. Everything
// navigates by route *name*, so no component needs to know the prefix.
const routes = [
    {
        path: '/',
        name: 'public',
        component: () => import('./pages/PublicPage.vue'),
    },
    {
        path: '/hi-developer',
        name: 'hi-developer',
        // Same component as '/' — the connect form renders as a popup over
        // the already-fetched public page rather than being its own page.
        component: () => import('./pages/PublicPage.vue'),
    },
    {
        path: adminUrl(),
        redirect: adminUrl('/mijn-agenda'),
    },
    {
        path: adminUrl('/mijn-agenda'),
        name: 'admin-agenda',
        component: () => import('./pages/AdminPage.vue'),
    },
    {
        path: adminUrl('/insights'),
        name: 'admin-insights',
        component: () => import('./pages/AdminPage.vue'),
    },
    {
        path: adminUrl('/edit-content'),
        name: 'admin-edit',
        component: () => import('./pages/AdminPage.vue'),
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('./pages/LoginPage.vue'),
    },
];

export default createRouter({
    history: createWebHistory(),
    routes,
});
