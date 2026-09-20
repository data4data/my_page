import { mount } from '@vue/test-utils';
import { markRaw } from 'vue';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import AdminLayout from '../../../js/components/admin/AdminLayout.vue';

// markRaw: a bare component object handed in as a prop gets made reactive,
// which Vue warns about and which costs a deep walk of the definition.
const icon = markRaw({ template: '<i />' });

// Settings carries `foot: true`, which is what puts it in the group pinned to
// the bottom of the rail rather than with the destinations.
const navItems = [
    { key: 'agenda', label: 'My agenda', icon },
    { key: 'insights', label: 'Insights', icon, count: 3 },
    { key: 'edit', label: 'Edit page', icon },
    { key: 'settings', label: 'Settings', icon, foot: true },
];

// Tracked and torn down after every test: the theme is held by a count in
// shared/theme.js, so a layout left mounted keeps holding data-theme and the
// next test starts from a state no real page is ever in.
const mounted = [];

const mountLayout = (activeKey = 'edit', profile = {}) => {
    const wrapper = mount(AdminLayout, {
        props: { navItems, activeKey, initials: 'AB', profile },
        attachTo: document.body,
    });

    mounted.push(wrapper);

    return wrapper;
};

const items = (wrapper) => wrapper.findAll('.admin-nav-item');

beforeEach(() => {
    localStorage.clear();
});

afterEach(() => {
    while (mounted.length) {
        mounted.pop().unmount();
    }
});

describe('AdminLayout rail', () => {
    it('renders every section, with the flagged ones in the foot group', () => {
        const wrapper = mountLayout();

        const text = items(wrapper).map((item) => item.text());

        for (const [index, item] of navItems.entries()) {
            expect(text[index]).toContain(item.label);
        }

        // Destinations above, Settings below the spacer.
        expect(wrapper.findAll('.admin-rail-nav .admin-nav-item')).toHaveLength(3);
        expect(wrapper.findAll('.admin-rail-foot .admin-nav-item')).toHaveLength(1);
        expect(wrapper.get('.admin-rail-foot .admin-nav-item').text()).toContain('Settings');
    });

    it('marks the active section for assistive technology, not just by colour', () => {
        const wrapper = mountLayout('insights');

        const active = items(wrapper).filter((item) => item.attributes('aria-current') === 'page');

        expect(active).toHaveLength(1);
        expect(active[0].text()).toContain('Insights');
    });

    it('navigates from either group', async () => {
        const wrapper = mountLayout();

        await wrapper.findAll('.admin-rail-nav .admin-nav-item')[0].trigger('click');
        await wrapper.get('.admin-rail-foot .admin-nav-item').trigger('click');

        expect(wrapper.emitted('navigate')).toEqual([['agenda'], ['settings']]);
    });

    // The label is clipped by CSS rather than removed, so the icon-only rail
    // still announces what each row is.
    it('keeps every label in the accessibility tree', () => {
        const wrapper = mountLayout();

        for (const item of items(wrapper)) {
            expect(item.text().trim()).not.toBe('');
        }
    });

    it('names the navigation landmark', () => {
        const wrapper = mountLayout();

        for (const nav of wrapper.findAll('nav')) {
            expect(nav.attributes('aria-label')).toBeTruthy();
        }
    });

    it('drops the language switcher when the profile turns it off', () => {
        const label = (wrapper) => wrapper.findAll('[role="radiogroup"]').map((group) => group.attributes('aria-label'));

        expect(label(mountLayout('edit', {}))).toContain('Language');
        expect(label(mountLayout('edit', { show_language_toggle: false }))).not.toContain('Language');
    });
});

describe('AdminLayout pin', () => {
    // Touch has neither hover nor focus, so the chevron is the only way to
    // open a collapsed rail — and it has to survive navigating.
    it('toggles the pin and remembers it', async () => {
        const wrapper = mountLayout();
        const pin = wrapper.get('.admin-rail-pin');

        expect(wrapper.get('aside').classes()).not.toContain('pinned');

        await pin.trigger('click');

        expect(wrapper.get('aside').classes()).toContain('pinned');
        expect(pin.attributes('aria-pressed')).toBe('true');
        expect(localStorage.getItem('workspace-rail-pinned')).toBe('1');
    });

    it('starts pinned when it was left pinned', () => {
        localStorage.setItem('workspace-rail-pinned', '1');

        expect(mountLayout().get('aside').classes()).toContain('pinned');
    });
});

describe('AdminLayout theme', () => {
    // data-theme is what selects the dark half of every light-dark() in
    // theme.css. The public page shares forms.css and the overlays that append
    // to <body>, so the attribute must not outlive the workspace.
    it('holds data-theme while mounted and releases it on unmount', () => {
        mountLayout();

        expect(document.documentElement.getAttribute('data-theme')).toBeTruthy();

        mounted.pop().unmount();

        expect(document.documentElement.hasAttribute('data-theme')).toBe(false);
    });

    it('keeps holding it while another layout is still mounted', () => {
        mountLayout();
        mountLayout();

        mounted.pop().unmount();

        // A route change can mount the next layout before the previous one
        // tears down; a boolean here would strip the attribute off the layout
        // that had just asked for it.
        expect(document.documentElement.getAttribute('data-theme')).toBeTruthy();

        mounted.pop().unmount();

        expect(document.documentElement.hasAttribute('data-theme')).toBe(false);
    });
});
