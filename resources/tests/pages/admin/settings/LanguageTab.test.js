import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const apiFetch = vi.fn();
const success = vi.fn();

// admin-path.js reads the tag once at module load, and vi.mock is hoisted
// above ordinary statements — so writing the shell here is what puts it in
// place before anything imports that module.
vi.hoisted(() => {
    document.head.innerHTML = '<meta name="admin-path" content="test-workspace">';
});

vi.mock('../../../../js/shared/api', async (importOriginal) => ({
    ...(await importOriginal()),
    apiFetch: (...args) => apiFetch(...args),
    reportError: vi.fn(),
}));

vi.mock('../../../../js/shared/toast', () => ({
    useToast: () => ({ success, error: vi.fn() }),
}));

const LanguageTab = (await import('../../../../js/pages/admin/settings/LanguageTab.vue')).default;

const stubs = {
    AppPillSwitch: { template: '<div />' },
    AppSelect: {
        name: 'AppSelect',
        props: ['modelValue', 'options'],
        emits: ['update:modelValue'],
        template: '<select @change="$emit(\'update:modelValue\', $event.target.value)" />',
    },
};

const mountTab = () => mount(LanguageTab, {
    props: { profile: { default_language: 'en', show_language_toggle: true } },
    global: { stubs },
});

describe('LanguageTab timezone', () => {
    beforeEach(() => {
        apiFetch.mockReset();
        success.mockReset();
        apiFetch.mockResolvedValue({ timezone: 'UTC', options: ['UTC', 'America/New_York'] });
    });

    it('offers the zones the server sent rather than a list of its own', async () => {
        const wrapper = mountTab();
        await flushPromises();

        expect(apiFetch).toHaveBeenCalledWith('/test-workspace/timezone');
        expect(wrapper.findComponent({ name: 'AppSelect' }).props('options')).toEqual([
            { value: 'UTC', label: 'UTC' },
            // Underscores read badly in a menu, so the label drops them.
            { value: 'America/New_York', label: 'America/New York' },
        ]);
    });

    it('saves the moment a zone is picked, rather than waiting for the Save button', async () => {
        const wrapper = mountTab();
        await flushPromises();

        wrapper.findComponent({ name: 'AppSelect' }).vm.$emit('update:modelValue', 'America/New_York');
        await flushPromises();

        expect(apiFetch).toHaveBeenLastCalledWith('/test-workspace/timezone', {
            method: 'PUT',
            body: { timezone: 'America/New_York' },
        });
        expect(success).toHaveBeenCalled();
    });

    it('puts the row back when the save is refused, so it never shows a zone nothing reports in', async () => {
        const wrapper = mountTab();
        await flushPromises();

        apiFetch.mockRejectedValueOnce(new Error('nope'));
        wrapper.findComponent({ name: 'AppSelect' }).vm.$emit('update:modelValue', 'America/New_York');
        await flushPromises();

        expect(wrapper.findComponent({ name: 'AppSelect' }).props('modelValue')).toBe('UTC');
        expect(success).not.toHaveBeenCalled();
    });
});
