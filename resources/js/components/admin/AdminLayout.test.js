import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import AdminLayout from './AdminLayout.vue';

const navItems = [
    { key: 'agenda', label: 'My agenda', icon: { template: '<i />' } },
    { key: 'insights', label: 'Insights', icon: { template: '<i />' } },
    { key: 'edit', label: 'Edit page', icon: { template: '<i />' } },
    { key: 'settings', label: 'Settings', icon: { template: '<i />' } },
];

const mountLayout = (activeKey = 'edit') => mount(AdminLayout, {
    props: { navItems, activeKey, initials: 'AB', profile: {} },
    global: { stubs: { AppButton: true, LogOut: true } },
});

const railButtons = (wrapper) => wrapper.findAll('.admin-nav-item');
const barButtons = (wrapper) => wrapper.findAll('.admin-bottom-nav-item');

describe('AdminLayout navigation', () => {
    // Small screens get a bottom bar instead of the stacked rail.
    it('renders both the rail and the bottom bar, each with every section', () => {
        const wrapper = mountLayout();

        expect(railButtons(wrapper)).toHaveLength(navItems.length);
        expect(barButtons(wrapper)).toHaveLength(navItems.length);
        expect(barButtons(wrapper).map((b) => b.text())).toEqual(navItems.map((i) => i.label));
    });

    it('shows exactly one of the two at any width', () => {
        const wrapper = mountLayout();

        expect(wrapper.get('aside').classes()).toContain('hidden');
        expect(wrapper.get('aside').classes()).toContain('lg:block');
        expect(wrapper.get('.admin-bottom-nav').classes()).toContain('lg:hidden');
    });

    it('marks the active section for assistive technology, not just by colour', () => {
        const wrapper = mountLayout('insights');

        const active = barButtons(wrapper).filter((b) => b.attributes('aria-current') === 'page');
        expect(active).toHaveLength(1);
        expect(active[0].text()).toBe('Insights');

        // The rail says the same thing.
        expect(railButtons(wrapper).filter((b) => b.attributes('aria-current') === 'page')).toHaveLength(1);
    });

    it('navigates from either one', async () => {
        const wrapper = mountLayout();

        await barButtons(wrapper)[0].trigger('click');
        await railButtons(wrapper)[3].trigger('click');

        expect(wrapper.emitted('navigate')).toEqual([['agenda'], ['settings']]);
    });

    it('names both navigation landmarks, so a screen reader can tell them apart from the page', () => {
        const wrapper = mountLayout();

        for (const nav of wrapper.findAll('nav')) {
            expect(nav.attributes('aria-label')).toBeTruthy();
        }
    });
});

describe('AdminLayout sticky rail', () => {
    // The divider has to reach the bottom, so only the nav travels.
    it('sticks the nav, not the aside', () => {
        const wrapper = mountLayout();

        expect(wrapper.get('aside nav').classes()).toContain('admin-rail-nav');
        expect(wrapper.get('aside').classes()).not.toContain('admin-rail-nav');
    });

    it('falls back to the stylesheet header height when nothing has measured one', () => {
        // jsdom has no ResizeObserver, same as the page's first paint.
        const wrapper = mountLayout();

        expect(wrapper.get('main').attributes('style')).toBeUndefined();
    });

    it('writes the measured header height once it has one', async () => {
        const observed = [];
        vi.stubGlobal('ResizeObserver', class {
            constructor(callback) { this.callback = callback; observed.push(this); }
            observe(target) { this.target = target; }
            disconnect() {}
        });

        const wrapper = mountLayout();
        expect(observed).toHaveLength(1);

        observed[0].callback([{ target: { getBoundingClientRect: () => ({ height: 91.4 }) } }]);
        await wrapper.vm.$nextTick();

        expect(wrapper.get('main').attributes('style')).toContain('--admin-header: 91px');

        vi.unstubAllGlobals();
    });
});
