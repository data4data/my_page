import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CategoryModal from '../../../js/pages/admin/settings/CategoryModal.vue';

// Only one level of nesting is supported, so "Work" (which has a child) must
// never be offered as a parent — doing so would create a grandchild that no
// view renders.
const categories = [
    { id: 1, name: 'Work', parent_id: null },
    { id: 2, name: 'Deep work', parent_id: 1 },
    { id: 3, name: 'Admin', parent_id: null },
];

const stubs = {
    AppInput: { template: '<input />' },
    AppIconSelect: { template: '<div />' },
    AppButton: { template: '<button><slot /></button>' },
    AppSelect: { name: 'AppSelect', props: ['options', 'modelValue'], template: '<select />' },
    X: true,
};

const mountModal = (props = {}) => mount(CategoryModal, {
    props: { categories, ...props },
    global: { stubs },
});

const parentOptionsOf = (wrapper) => wrapper.findComponent({ name: 'AppSelect' }).props('options');

const labels = (wrapper) => parentOptionsOf(wrapper).map((option) => option.label);

describe('CategoryModal parent options', () => {
    it('offers only top-level categories that have no children', () => {
        const wrapper = mountModal();

        expect(labels(wrapper)).toContain('Admin');
        expect(labels(wrapper)).not.toContain('Work');
    });

    it('never offers a subcategory as a parent', () => {
        const wrapper = mountModal();

        expect(labels(wrapper)).not.toContain('Deep work');
    });

    it('never offers the category being edited as its own parent', () => {
        const wrapper = mountModal({ category: categories[2] });

        expect(labels(wrapper)).not.toContain('Admin');
    });

    it('always offers the no-parent option', () => {
        const wrapper = mountModal();

        expect(parentOptionsOf(wrapper)[0].value).toBeNull();
    });
});

describe('CategoryModal title', () => {
    it('reads as adding when creating from scratch', () => {
        const wrapper = mountModal();

        expect(wrapper.find('h2').text()).toBe('Add category');
    });

    it('reads as adding when creating a subcategory from a parent', () => {
        // AgendaPage passes a stub carrying only parent_id to pre-select the
        // parent; that is still a create, not an edit.
        const wrapper = mountModal({ category: { parent_id: 1 } });

        expect(wrapper.find('h2').text()).toBe('Add category');
    });

    it('reads as editing for a real saved category', () => {
        const wrapper = mountModal({ category: categories[0] });

        expect(wrapper.find('h2').text()).toBe('Edit category');
    });
});
