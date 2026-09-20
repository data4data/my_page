import { createRouter, createWebHistory } from 'vue-router';

/**
 * The visit card's routes, and only those. Nothing here may import anything
 * under pages/admin: the public bundle cannot contain what it never references.
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
        // Same component as '/': the connect form is a popup over the page.
        meta: { connect: true },
        component: () => import('./pages/public/PublicPage.vue'),
    },
    // The same two pages under an explicit language. The default language keeps
    // the bare path, so only the other locales reach these.
    {
        path: '/:locale(en|nl)',
        name: 'public-language',
        component: () => import('./pages/public/PublicPage.vue'),
    },
    {
        path: '/:locale(en|nl)/hi-developer',
        name: 'hi-developer-language',
        meta: { connect: true },
        component: () => import('./pages/public/PublicPage.vue'),
    },
];

export default createRouter({
    history: createWebHistory(),
    routes,
});
