import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PrimeVue from 'primevue/config';
import PublicPage from './public/PublicPage.vue';
import AdminPage from './admin/AdminPage.vue';

// Nothing else mounts these two, so a broken import or a template referring
// to something gone would only surface in a browser. `npm run build` proves
// the files resolve; this proves they render.

// Both call useRoute()/useRouter(), so the module has to be mocked: a
// `mocks: { $route }` option leaves useRoute() undefined.
// AdminPage picks its section from the route name, so tests move this.
const routeName = { current: 'admin-edit' };

vi.mock('vue-router', () => ({
    useRoute: () => ({ get name() { return routeName.current; }, path: '/', params: {}, query: {} }),
    useRouter: () => ({ push: vi.fn() }),
}));

const payload = {
    profile: {
        initials: 'AB',
        role: { en: 'Developer', nl: 'Ontwikkelaar' },
        headline: { en: 'Testable headline', nl: 'Testbare kop' },
        summary: { en: 'Summary', nl: 'Samenvatting' },
        default_language: 'en',
        show_language_toggle: true,
        social_links: [
            { label: 'Both', url: 'https://both.test', icon: 'github', in_rail: true, in_footer: true },
            { label: 'Rail only', url: 'https://rail.test', icon: 'link', in_rail: true, in_footer: false },
            { label: 'Footer only', url: 'https://footer.test', icon: 'link', in_rail: false, in_footer: true },
            { label: 'Hidden', url: 'https://hidden.test', icon: 'link', in_rail: false, in_footer: false },
        ],
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

    // A render error does not fail a mount, so collect and assert. PrimeVue
    // is installed as app.js does it: stubbing the App* components out would
    // skip most of what this exercises.
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

        // Set per link, so each place draws its own set.
        const hrefs = (selector) => wrapper.findAll(selector).map((a) => a.attributes('href'));

        expect(hrefs('.social-rail a')).toEqual(['https://both.test', 'https://rail.test']);
        expect(hrefs('.social-footer a')).toEqual(['https://both.test', 'https://footer.test']);
        expect(wrapper.html()).not.toContain('hidden.test');

        // Between the two notes, which is what centres them.
        const footer = wrapper.get('.site-footer').element;
        const order = [...footer.children].map((child) => child.className);
        expect(order[0]).toContain('site-footer-note');
        expect(order[1]).toContain('social-footer');
        expect(order[2]).toContain('site-footer-note');
    });

    it('PublicPage drops each place independently', async () => {
        const railOnly = structuredClone(payload);
        railOnly.profile.social_links = [
            { label: 'Rail only', url: 'https://rail.test', icon: 'link', in_rail: true, in_footer: false },
        ];
        global.fetch = vi.fn(() => Promise.resolve({ ok: true, json: () => Promise.resolve(railOnly) }));

        const wrapper = mountPage(PublicPage);
        await flush();

        expect(errors).toEqual([]);
        expect(wrapper.find('.social-rail').exists()).toBe(true);
        // Nothing wants the footer, so it goes even though a link is on show
        // in the rail.
        expect(wrapper.find('.social-footer').exists()).toBe(false);
        expect(wrapper.find('.site-footer-spacer').exists()).toBe(true);
    });

    it('PublicPage drops the rail and the footer row when no link is visible', async () => {
        global.fetch = vi.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({
                ...structuredClone(payload),
                profile: { ...structuredClone(payload).profile, social_links: [] },
            }),
        }));

        const wrapper = mountPage(PublicPage);
        await flush();

        expect(errors).toEqual([]);
        expect(wrapper.find('.social-rail').exists()).toBe(false);
        expect(wrapper.find('.social-footer').exists()).toBe(false);

        // The middle track stays open, so the notes stay at the edges.
        expect(wrapper.find('.site-footer-spacer').exists()).toBe(true);
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
