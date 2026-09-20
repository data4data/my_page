import { createRouter, createWebHistory } from 'vue-router';
import { adminUrl } from './shared/admin-path';

/**
 * The workspace's routes, and only those. The prefix is per install and read
 * from a meta tag through adminUrl(), and components navigate by route name,
 * so nothing here or in a component hardcodes it.
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
        path: adminUrl('/settings'),
        name: 'admin-settings',
        component: () => import('./pages/admin/AdminPage.vue'),
    },
    {
        // Behind the prefix like everything it leads to, so there is no login
        // form at the guessable /login.
        path: adminUrl('/login'),
        name: 'login',
        component: () => import('./pages/admin/LoginPage.vue'),
    },
];

export default createRouter({
    history: createWebHistory(),
    routes,
});
