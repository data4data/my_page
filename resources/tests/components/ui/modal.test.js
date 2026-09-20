import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AdminModal from '../../../js/components/ui/AdminModal.vue';
import PublicModal from '../../../js/components/ui/PublicModal.vue';

const stubs = { X: true };

// Every behaviour test runs against both shells, so one that forgets to call
// the composable fails rather than passing by association.
const shells = [
    ['AdminModal', AdminModal, '.admin-modal', '.admin-modal-close'],
    ['PublicModal', PublicModal, '.public-modal', '.public-modal-close'],
];

const pressKey = (key, init = {}) => document.dispatchEvent(
    new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true, ...init }),
);

describe.each(shells)('%s', (_name, Shell, cardSelector, closeSelector) => {
    const mountModal = (props = {}, slot = '<button class="inner">Save</button>') => mount(Shell, {
        props,
        slots: { default: slot },
        attachTo: document.body,
        global: { stubs },
    });

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

    // A Select inside handles its own Escape; closing the editor out from
    // under an open dropdown would lose the user's work.
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

        await wrapper.get(cardSelector).trigger('click');
        expect(wrapper.emitted('close')).toBeUndefined();

        await wrapper.get('.modal-overlay').trigger('click');
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
        expect(document.activeElement).toBe(wrapper.get(closeSelector).element);
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

// The differences the split exists for.
describe('AdminModal', () => {
    const mountAdmin = (props = {}, slots = {}) => mount(AdminModal, {
        props,
        slots: { default: '<p>body</p>', ...slots },
        attachTo: document.body,
        global: { stubs },
    });

    it('renders the head bands it is given and nothing it is not', () => {
        const wrapper = mountAdmin({ eyebrow: 'Agenda', title: 'Edit task' });

        expect(wrapper.get('.admin-modal-eyebrow').text()).toBe('Agenda');
        expect(wrapper.get('.admin-modal-title').text()).toBe('Edit task');
        expect(wrapper.find('.admin-modal-subtitle').exists()).toBe(false);
    });

    it('names itself by its visible title when no label is given', () => {
        const wrapper = mountAdmin({ title: 'Edit task' });

        expect(wrapper.get('[role="dialog"]').attributes('aria-label')).toBe('Edit task');
    });

    // A modal that only reads should not get an empty strip along the bottom.
    it('draws the action bar only when something fills it', () => {
        expect(mountAdmin().find('.admin-modal-bar').exists()).toBe(false);
        expect(mountAdmin({}, { footer: '<button>Save</button>' }).find('.admin-modal-bar').exists()).toBe(true);
    });

    it('widens for the size it is asked for', () => {
        expect(mountAdmin().get('.admin-modal').classes()).not.toContain('admin-modal-wide');
        expect(mountAdmin({ size: 'wide' }).get('.admin-modal').classes()).toContain('admin-modal-wide');
        expect(mountAdmin({ size: 'narrow' }).get('.admin-modal').classes()).toContain('admin-modal-narrow');
    });
});

describe('the split between the two shells', () => {
    const css = readFileSync(join(process.cwd(), 'resources/css/overlays.css'), 'utf8');

    const rule = (selector) => css.match(new RegExp(`\\${selector}\\s*\\{[^}]*\\}`))?.[0] ?? '';

    // Every colour has to come from a token with two halves: a literal stays
    // light in dark mode.
    it('draws the workspace card from the surface tokens only', () => {
        const workspace = ['.admin-modal', '.admin-modal-head', '.admin-modal-bar', '.admin-modal-close']
            .map(rule)
            .join('\n');

        expect(workspace).not.toMatch(/#[0-9a-f]{3,8}\b/i);
        expect(workspace).not.toMatch(/\b(bg-white|bg-cream|border-sand|text-ink)\b/);
        expect(workspace).toContain('var(--color-sheet)');
    });

    // The one card on the visit card that renders forms.css fields.
    it('keeps the visit card on the brand palette', () => {
        const publicCard = rule('.public-modal');

        expect(publicCard).toContain('bg-cream');
        expect(publicCard).toContain('--color-field');
        expect(publicCard).not.toContain('var(--color-sheet)');
    });
});
