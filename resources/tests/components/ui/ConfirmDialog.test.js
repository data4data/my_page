import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ConfirmDialog from '../../../js/components/ui/ConfirmDialog.vue';
import { useConfirm } from '../../../js/shared/confirm';

const { confirm, respond } = useConfirm();

const stubs = {
    AppButton: { template: '<button><slot /></button>' },
    X: true,
};

afterEach(() => {
    respond(false);
});

describe('ConfirmDialog', () => {
    it('resolves false when Escape is pressed', async () => {
        const answer = confirm({ message: 'Delete this?' });
        const wrapper = mount(ConfirmDialog, { global: { stubs } });
        await wrapper.vm.$nextTick();

        // A real user presses Escape without first clicking inside the dialog,
        // so the key event lands on document, not on the overlay element.
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await wrapper.vm.$nextTick();

        await expect(answer).resolves.toBe(false);
        wrapper.unmount();
    });

    it('resolves true when the confirm button is pressed', async () => {
        const answer = confirm({ message: 'Delete this?' });
        const wrapper = mount(ConfirmDialog, { global: { stubs } });
        await wrapper.vm.$nextTick();

        await wrapper.findAll('button').at(-1).trigger('click');

        await expect(answer).resolves.toBe(true);
        wrapper.unmount();
    });

    it('resolves false when the overlay itself is clicked', async () => {
        const answer = confirm({ message: 'Delete this?' });
        const wrapper = mount(ConfirmDialog, { global: { stubs } });
        await wrapper.vm.$nextTick();

        await wrapper.find('.modal-overlay').trigger('click');

        await expect(answer).resolves.toBe(false);
        wrapper.unmount();
    });

    it('does not leave a stale listener behind after closing', async () => {
        const removeSpy = vi.spyOn(document, 'removeEventListener');

        const answer = confirm({ message: 'Delete this?' });
        const wrapper = mount(ConfirmDialog, { global: { stubs } });
        await wrapper.vm.$nextTick();

        respond(false);
        await answer;
        await wrapper.vm.$nextTick();
        wrapper.unmount();

        expect(removeSpy).toHaveBeenCalledWith('keydown', expect.any(Function));
    });
});
