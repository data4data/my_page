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
    AppCheckbox: { name: 'AppCheckbox', props: ['modelValue'], emits: ['update:modelValue'], template: '<input type="checkbox" />' },
    // No @click re-emit: the stub's root is a real <button>, so the listener
    // falls through. Emitting too would fire every action twice.
    AppButton: { template: '<button><slot /></button>' },
    Plus: true,
};

const mountTab = (profile) => mount(SocialLinksTab, { props: { profile }, global: { stubs } });

const cards = (wrapper) => wrapper.findAll('.editable-card');
const addButton = (wrapper) => wrapper.findAll('button').at(-1);

describe('SocialLinksTab', () => {
    it('lists one card per link, ones shown nowhere included', () => {
        const profile = {
            social_links: [
                { label: 'GitHub', url: 'https://github.test', icon: 'github', in_rail: true, in_footer: true },
                { label: 'Old', url: 'https://old.test', icon: 'link', in_rail: false, in_footer: false },
            ],
        };

        // Both, or one switched off everywhere could never come back.
        expect(cards(mountTab(profile))).toHaveLength(2);
    });

    it('copes with a profile that has never had links', async () => {
        const profile = {};
        const wrapper = mountTab(profile);

        expect(cards(wrapper)).toHaveLength(0);

        await addButton(wrapper).trigger('click');

        expect(profile.social_links).toEqual([{ label: '', url: '', icon: 'link', in_rail: true, in_footer: true }]);
    });

    it('adds links shown in both places, so a new one is not silently invisible', async () => {
        const profile = { social_links: [] };
        const wrapper = mountTab(profile);

        await addButton(wrapper).trigger('click');

        expect(profile.social_links[0].in_rail).toBe(true);
        expect(profile.social_links[0].in_footer).toBe(true);
    });

    it('toggles the two places independently', async () => {
        const profile = {
            social_links: [{ label: 'One', url: 'https://one.test', icon: 'link', in_rail: true, in_footer: true }],
        };
        const wrapper = mountTab(profile);

        const boxes = () => cards(wrapper)[0].findAllComponents({ name: 'AppCheckbox' });

        await boxes()[0].vm.$emit('update:modelValue', false);

        expect(profile.social_links[0].in_rail).toBe(false);
        // Turning one off must leave the other alone.
        expect(profile.social_links[0].in_footer).toBe(true);

        await boxes()[1].vm.$emit('update:modelValue', false);
        expect(profile.social_links[0].in_footer).toBe(false);
    });

    // A link saved before the split carries only is_visible; an unticked box
    // would misreport a link that is on show.
    it('shows a link from before the split as ticked in both places', () => {
        const profile = {
            social_links: [{ label: 'Legacy', url: 'https://legacy.test', icon: 'link', is_visible: true }],
        };
        const wrapper = mountTab(profile);

        const boxes = cards(wrapper)[0].findAllComponents({ name: 'AppCheckbox' });

        expect(boxes[0].props('modelValue')).toBe(true);
        expect(boxes[1].props('modelValue')).toBe(true);
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
