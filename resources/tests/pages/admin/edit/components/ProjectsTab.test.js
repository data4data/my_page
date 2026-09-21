import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const apiFetch = vi.fn();
const success = vi.fn();

vi.hoisted(() => {
    document.head.innerHTML = '<meta name="admin-path" content="test-workspace">';
});

vi.mock('../../../../../js/shared/api', async (importOriginal) => ({
    ...(await importOriginal()),
    apiFetch: (...args) => apiFetch(...args),
    reportError: vi.fn(),
}));

vi.mock('../../../../../js/shared/toast', () => ({
    useToast: () => ({ success, error: vi.fn() }),
}));

const ProjectsTab = (await import('../../../../../js/pages/admin/edit/components/ProjectsTab.vue')).default;

const stubs = {
    AppInput: { template: '<input />' },
    AppTextarea: { template: '<textarea />' },
    AppButton: { template: '<button><slot /></button>' },
    AppLanguageCards: { template: '<div />' },
    EditableCard: { template: '<section><slot /></section>' },
    AppSelect: {
        name: 'AppSelect',
        props: ['modelValue', 'options'],
        template: '<select />',
    },
    AppMultiSelect: {
        name: 'AppMultiSelect',
        props: ['modelValue', 'options'],
        emits: ['update:modelValue', 'filter'],
        template: '<div class="multiselect"><slot name="emptyfilter" /></div>',
    },
};

const project = () => ({
    title: { en: 'One', nl: 'Een' },
    summary: { en: '', nl: '' },
    result: { en: '', nl: '' },
    tags: ['Laravel'],
    visual_style: 'dashboard',
});

const mountTab = (projects = [project()]) => mount(ProjectsTab, {
    props: { projects, profile: { default_language: 'en' } },
    global: { stubs },
});

describe('ProjectsTab', () => {
    beforeEach(() => {
        apiFetch.mockReset();
        success.mockReset();
        apiFetch.mockResolvedValue({ tags: ['Laravel', 'Vue.js'] });
    });

    it('offers the visual styles the CSS has a rule for', async () => {
        const wrapper = mountTab();
        await flushPromises();

        expect(wrapper.findComponent({ name: 'AppSelect' }).props('options').map((option) => option.value))
            .toEqual(['dashboard', 'flow', 'cms']);
    });

    it('offers the vocabulary the server sent rather than free text', async () => {
        const wrapper = mountTab();
        await flushPromises();

        expect(apiFetch).toHaveBeenCalledWith('/test-workspace/tags');
        expect(wrapper.findComponent({ name: 'AppMultiSelect' }).props('options'))
            .toEqual([{ value: 'Laravel', label: 'Laravel' }, { value: 'Vue.js', label: 'Vue.js' }]);
    });

    it('adds a searched-for word to the vocabulary and to the project it was added from', async () => {
        const projects = [project()];
        const wrapper = mountTab(projects);
        await flushPromises();

        const select = wrapper.findComponent({ name: 'AppMultiSelect' });
        select.vm.$emit('filter', { value: 'Livewire' });
        await flushPromises();

        apiFetch.mockResolvedValueOnce({ tag: 'Livewire' });
        await wrapper.find('.field-select-add').trigger('click');
        await flushPromises();

        expect(apiFetch).toHaveBeenLastCalledWith('/test-workspace/tags', {
            method: 'POST',
            body: { name: 'Livewire' },
        });
        expect(projects[0].tags).toEqual(['Laravel', 'Livewire']);
        expect(success).toHaveBeenCalled();
    });

    it('does not post an empty search', async () => {
        const wrapper = mountTab();
        await flushPromises();

        apiFetch.mockClear();
        await wrapper.find('.field-select-add').trigger('click');
        await flushPromises();

        expect(apiFetch).not.toHaveBeenCalled();
    });
});
