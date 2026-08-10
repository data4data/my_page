import { createRouter, createWebHistory } from 'vue-router';

// Independent, lazily-loaded pages. Laravel's own routes (routes/web.php)
// already serve every path below to the same Blade shell — this router just
// decides which Vue page mounts for each, so e.g. the admin editor's JS
// never ships to public visitors.
//
// The real admin editor intentionally lives at /control-room-ao (not
// /admin) and is never linked from the public page — see PublicPage.vue.
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
        path: '/control-room-ao',
        name: 'admin',
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
