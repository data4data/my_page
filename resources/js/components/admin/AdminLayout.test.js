import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
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
    // Small screens get a bottom bar instead of the stacked rail, which was
    // spending most of a phone's first screenful on navigation.
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
