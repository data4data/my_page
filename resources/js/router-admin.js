import { createRouter, createWebHistory } from 'vue-router';
import { adminUrl } from './shared/admin-path';

/**
 * The workspace's routes, and only those. Served by the app-admin.js bundle,
 * which the Blade shell loads only for a request inside the workspace prefix.
 *
 * That prefix is per install (ADMIN_PATH, see config/admin.php) and is read
 * from a meta tag through adminUrl(), so this file and routes/web.php cannot
 * disagree about it. Every component navigates by route *name*, so none of
 * them needs to know the prefix either.
 */
const routes = [
    {
        path: adminUrl(),
        redirect: adminUrl('/mijn-agenda'),
    },
    {
        path: adminUrl('/mijn-agenda'),
        name: 'admin-agenda',
        component: () => import('./pages/admin/AdminPage.vue'),
    },
    {
        path: adminUrl('/insights'),
        name: 'admin-insights',
        component: () => import('./pages/admin/AdminPage.vue'),
    },
    {
        path: adminUrl('/edit-content'),
        name: 'admin-edit',
        component: () => import('./pages/admin/AdminPage.vue'),
    },
    {
        // Settings the owner changes rarely: what language the page opens in,
        // two-step sign-in, and the saved versions to roll back to. Separate
        // from Edit page, which is where the content itself is written.
        path: adminUrl('/settings'),
        name: 'admin-settings',
        component: () => import('./pages/admin/AdminPage.vue'),
    },
    {
        // Behind the workspace prefix like everything else it leads to, so
        // there is no login form at the guessable /login. See routes/web.php.
        path: adminUrl('/login'),
        name: 'login',
        component: () => import('./pages/admin/LoginPage.vue'),
    },
];

export default createRouter({
    history: createWebHistory(),
    routes,
});
