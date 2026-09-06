import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AppModal from './AppModal.vue';

const stubs = { X: true };

const mountModal = (props = {}, slot = '<button class="inner">Save</button>') => mount(AppModal, {
    props,
    slots: { default: slot },
    attachTo: document.body,
    global: { stubs },
});

const pressKey = (key, init = {}) => document.dispatchEvent(
    new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true, ...init }),
);

describe('AppModal', () => {
    it('announces itself as a modal dialog with a name', () => {
        const wrapper = mountModal({ label: 'Edit task' });
        const dialog = wrapper.get('[role="dialog"]');

        expect(dialog.attributes('aria-modal')).toBe('true');
        expect(dialog.attributes('aria-label')).toBe('Edit task');
    });

    it('closes on Escape, which none of these modals used to do', async () => {
        const wrapper = mountModal();

        pressKey('Escape');
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    // A PrimeVue Select inside the modal handles its own Escape; closing the
    // whole editor out from under an open dropdown would lose the user's work.
    it('ignores an Escape another component already handled', async () => {
        const wrapper = mountModal();

        const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
        event.preventDefault();
        document.dispatchEvent(event);
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('close')).toBeUndefined();
    });

    it('closes when the backdrop is clicked but not the card', async () => {
        const wrapper = mountModal();

        await wrapper.get('.connect-modal').trigger('click');
        expect(wrapper.emitted('close')).toBeUndefined();

        await wrapper.get('.connect-overlay').trigger('click');
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('moves focus into the dialog and hands it back on close', async () => {
        const opener = document.createElement('button');
        document.body.appendChild(opener);
        opener.focus();
        expect(document.activeElement).toBe(opener);

        const wrapper = mountModal();
        await wrapper.vm.$nextTick();
        expect(wrapper.get('[role="dialog"]').element).toBe(document.activeElement);

        wrapper.unmount();
        expect(document.activeElement).toBe(opener);

        opener.remove();
    });

    it('wraps Tab at the end of the dialog rather than letting focus escape', async () => {
        const wrapper = mountModal({}, '<button class="first">One</button><button class="last">Two</button>');
        await wrapper.vm.$nextTick();

        const last = wrapper.get('.last').element;
        last.focus();

        pressKey('Tab');
        // The close button is the first focusable element in the card.
        expect(document.activeElement).toBe(wrapper.get('.connect-close').element);
    });

    it('leaves focus alone when it sits outside the card, as a portalled dropdown does', async () => {
        const wrapper = mountModal();
        await wrapper.vm.$nextTick();

        const outside = document.createElement('button');
        document.body.appendChild(outside);
        outside.focus();

        pressKey('Tab');

        expect(document.activeElement).toBe(outside);
        outside.remove();
    });
});
