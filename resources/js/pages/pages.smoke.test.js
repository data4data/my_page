import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PrimeVue from 'primevue/config';
import PublicPage from './public/PublicPage.vue';
import AdminPage from './admin/AdminPage.vue';

// Both pages are top-level route components: nothing else mounts them, so a
// broken import or a template referring to something that no longer exists
// would only surface in a browser. `npm run build` proves the files resolve;
// this proves they actually render. Added when pages/ was split into public/
// and admin/ — exactly the kind of move that resolves fine and still breaks
// at runtime.

// Both pages call useRoute()/useRouter() through the Composition API, so the
// module has to be mocked — a `mocks: { $route }` option does nothing here and
// leaves useRoute() undefined.
// AdminPage picks its section from the route name, so the mock is a ref the
// tests move rather than a fixed value.
const routeName = { current: 'admin-edit' };

vi.mock('vue-router', () => ({
    useRoute: () => ({ get name() { return routeName.current; }, path: '/', params: {}, query: {} }),
    useRouter: () => ({ push: vi.fn() }),
}));

const payload = {
    profile: {
        initials: 'OA',
        role: { en: 'Developer', nl: 'Ontwikkelaar' },
        headline: { en: 'Testable headline', nl: 'Testbare kop' },
        summary: { en: 'Summary', nl: 'Samenvatting' },
        default_language: 'en',
        show_language_toggle: true,
        social_links: [],
    },
    metrics: [],
    expertise_items: [],
    projects: [],
    process_steps: [],
    inquiries: [],
    revisions: [],
};

const flush = async () => {
    // Two ticks: the pages fetch on setup, then render from the result.
    await new Promise((resolve) => setTimeout(resolve, 0));
    await new Promise((resolve) => setTimeout(resolve, 0));
};

describe('page smoke tests', () => {
    let errors;

    beforeEach(() => {
        errors = [];
        routeName.current = 'admin-edit';
        global.fetch = vi.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve(structuredClone(payload)),
        }));
        document.head.innerHTML = '<meta name="admin-path" content="test-workspace">'
            + '<meta name="csrf-token" content="test-token">';
    });

    // A render error does not fail a mount by itself, so collect and assert.
    // PrimeVue is installed with the same options as app.js rather than
    // stubbing the App* components — stubbing them out would skip most of
    // what this test exists to exercise.
    const mountPage = (component) => mount(component, {
        global: {
            plugins: [[PrimeVue, { unstyled: true, locale: { firstDayOfWeek: 1 } }]],
            config: { errorHandler: (error) => errors.push(error) },
        },
    });

    it('PublicPage renders its content without errors', async () => {
        const wrapper = mountPage(PublicPage);
        await flush();

        expect(errors).toEqual([]);
        expect(wrapper.text()).toContain('Testable headline');
    });

    it('AdminPage renders the editor without errors', async () => {
        const wrapper = mountPage(AdminPage);
        await flush();

        expect(errors).toEqual([]);
        // The tab strip only exists once the payload has loaded and the shell
        // has swapped out of its loading state.
        expect(wrapper.text()).toContain('Projects');
        // Language and Content versions are Settings now, not page content.
        expect(wrapper.text()).not.toContain('Content versions');
    });

    it('AdminPage renders the settings section without errors', async () => {
        routeName.current = 'admin-settings';

        const wrapper = mountPage(AdminPage);
        await flush();

        expect(errors).toEqual([]);
        expect(wrapper.text()).toContain('Content versions');
        expect(wrapper.text()).toContain('Two-step sign-in');
    });
});
