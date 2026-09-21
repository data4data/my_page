import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import EditableCard from '../../js/components/EditableCard.vue';
import { copy } from '../../js/shared/i18n';

const stubs = {
    AppCheckbox: { template: '<label><slot /></label>' },
};

const mountCard = (props = {}, slots = {}) => mount(EditableCard, {
    props: { title: 'A metric', index: 1, total: 4, ...props },
    slots,
    global: { stubs },
});

describe('EditableCard head', () => {
    it('reads the position as "2 of 4"', () => {
        expect(mountCard().find('.item-card-position').text())
            .toBe(`2 ${copy('positionOf')} 4`);
    });

    /**
     * The number says what the arrows either side of it would do next, so it
     * belongs between them rather than off on its own.
     */
    it('puts the position between the two reorder arrows', () => {
        const tools = mountCard().find('.item-card-tools');
        const order = [...tools.element.children].map((child) => (
            child.classList.contains('item-card-position')
                ? 'position'
                : child.getAttribute('aria-label') ?? child.tagName.toLowerCase()
        ));

        const up = order.indexOf(copy('moveUp'));
        const down = order.indexOf(copy('moveDown'));

        expect(up).toBeGreaterThanOrEqual(0);
        expect(order.indexOf('position')).toBe(up + 1);
        expect(down).toBe(up + 2);
    });

    it('renders a head field beside the title rather than in the body', () => {
        const wrapper = mountCard({}, { 'head-field': '<span class="probe">12+</span>' });

        expect(wrapper.find('.item-card-head .probe').exists()).toBe(true);
        expect(wrapper.find('.item-card-body .probe').exists()).toBe(false);
    });

    it('leaves the Visible toggle out when the item has no visibility of its own', () => {
        expect(mountCard().find('.item-card-divider').exists()).toBe(false);
        expect(mountCard({ visible: true }).find('.item-card-divider').exists()).toBe(true);
    });
});
