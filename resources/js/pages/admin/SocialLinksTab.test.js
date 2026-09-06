import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SocialLinksTab from './SocialLinksTab.vue';

const stubs = {
    AppInput: {
        name: 'AppInput',
        props: ['modelValue'],
        emits: ['update:modelValue'],
        template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    },
    AppIconSelect: { name: 'AppIconSelect', props: ['modelValue'], template: '<select />' },
    AppCheckbox: { name: 'AppCheckbox', props: ['modelValue'], template: '<input type="checkbox" />' },
    // No @click re-emit: the stub's root is a real <button>, so the listener
    // falls through to it. Emitting as well would fire every action twice —
    // which for a reorder is a swap and a swap back, and looks like nothing
    // happening at all.
    AppButton: { template: '<button><slot /></button>' },
    Plus: true,
};

const mountTab = (profile) => mount(SocialLinksTab, { props: { profile }, global: { stubs } });

const cards = (wrapper) => wrapper.findAll('.editable-card');
const addButton = (wrapper) => wrapper.findAll('button').at(-1);

describe('SocialLinksTab', () => {
    it('lists one card per link, hidden ones included', () => {
        const profile = {
            social_links: [
                { label: 'GitHub', url: 'https://github.test', icon: 'github', is_visible: true },
                { label: 'Old', url: 'https://old.test', icon: 'link', is_visible: false },
            ],
        };

        // Both, or a link switched off could never be switched back on.
        expect(cards(mountTab(profile))).toHaveLength(2);
    });

    it('copes with a profile that has never had links', async () => {
        const profile = {};
        const wrapper = mountTab(profile);

        expect(cards(wrapper)).toHaveLength(0);

        await addButton(wrapper).trigger('click');

        expect(profile.social_links).toEqual([{ label: '', url: '', icon: 'link', is_visible: true }]);
    });

    it('adds visible links, so a new one is not silently switched off', async () => {
        const profile = { social_links: [] };
        const wrapper = mountTab(profile);

        await addButton(wrapper).trigger('click');

        expect(profile.social_links[0].is_visible).toBe(true);
    });

    it('reorders and removes in place, since these live on the profile', async () => {
        const profile = {
            social_links: [
                { label: 'One', url: 'https://one.test', icon: 'link', is_visible: true },
                { label: 'Two', url: 'https://two.test', icon: 'link', is_visible: true },
                { label: 'Three', url: 'https://three.test', icon: 'link', is_visible: true },
            ],
        };
        const wrapper = mountTab(profile);

        // Each card header carries move up, move down, then remove.
        const buttonsOf = (index) => cards(wrapper)[index].findAll('header button');

        await buttonsOf(0)[1].trigger('click');
        expect(profile.social_links.map((l) => l.label)).toEqual(['Two', 'One', 'Three']);

        await buttonsOf(2)[2].trigger('click');
        expect(profile.social_links.map((l) => l.label)).toEqual(['Two', 'One']);
    });

    it('refuses to move the first link up or the last one down', async () => {
        const profile = {
            social_links: [
                { label: 'One', url: 'https://one.test', icon: 'link', is_visible: true },
                { label: 'Two', url: 'https://two.test', icon: 'link', is_visible: true },
            ],
        };
        const wrapper = mountTab(profile);

        await cards(wrapper)[0].findAll('header button')[0].trigger('click');
        await cards(wrapper)[1].findAll('header button')[1].trigger('click');

        expect(profile.social_links.map((l) => l.label)).toEqual(['One', 'Two']);
    });
});
