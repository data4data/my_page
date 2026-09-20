import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import SocialLinksTab from '../../../js/pages/admin/SocialLinksTab.vue';

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

const link = (label, extra = {}) => ({
    label,
    url: `https://${label.toLowerCase()}.test`,
    icon: 'link',
    in_rail: true,
    in_footer: true,
    ...extra,
});

const mountTab = (links = []) => {
    const handlers = { addItem: vi.fn(), removeItem: vi.fn(), moveItem: vi.fn() };
    const wrapper = mount(SocialLinksTab, { props: { links, ...handlers }, global: { stubs } });

    return { wrapper, ...handlers };
};

const cards = (wrapper) => wrapper.findAll('.item-card');
const addButton = (wrapper) => wrapper.findAll('button').at(-1);

describe('SocialLinksTab', () => {
    it('lists one card per link, ones shown nowhere included', () => {
        const { wrapper } = mountTab([
            link('GitHub'),
            link('Nowhere', { in_rail: false, in_footer: false }),
        ]);

        // Both, or one switched off everywhere could never come back.
        expect(cards(wrapper)).toHaveLength(2);
    });

    it('renders no cards when there are none', () => {
        expect(cards(mountTab().wrapper)).toHaveLength(0);
    });

    // A child collection now, so the tab asks AdminPage rather than editing an
    // array on the profile in place.
    it('adds through the shared helper, shown in both places', async () => {
        const { wrapper, addItem } = mountTab([]);

        await addButton(wrapper).trigger('click');

        expect(addItem).toHaveBeenCalledWith('social_links', {
            label: '', url: '', icon: 'link', in_rail: true, in_footer: true,
        });
    });

    it('toggles the two places independently', async () => {
        const links = [link('One')];
        const { wrapper } = mountTab(links);

        const boxes = () => cards(wrapper)[0].findAllComponents({ name: 'AppCheckbox' });

        await boxes()[0].vm.$emit('update:modelValue', false);

        expect(links[0].in_rail).toBe(false);
        // Turning one off must leave the other alone.
        expect(links[0].in_footer).toBe(true);

        await boxes()[1].vm.$emit('update:modelValue', false);
        expect(links[0].in_footer).toBe(false);
    });

    it('hands reordering and removal to the shared helpers', async () => {
        const { wrapper, moveItem, removeItem } = mountTab([link('One'), link('Two'), link('Three')]);

        // Each card header carries move up, move down, then remove.
        const buttonsOf = (index) => cards(wrapper)[index].findAll('header button');

        await buttonsOf(0)[1].trigger('click');
        expect(moveItem).toHaveBeenCalledWith('social_links', 0, 1);

        await buttonsOf(2)[2].trigger('click');
        expect(removeItem).toHaveBeenCalledWith('social_links', 2);
    });
});
