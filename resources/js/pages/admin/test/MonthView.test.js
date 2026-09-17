import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import MonthView from '../agenda/MonthView.vue';

const monthStart = new Date(2026, 7, 1); // 1 Aug 2026

const tasks = [
    {
        id: 1,
        title: 'Ship the release',
        start_datetime: '2026-08-12 09:00:00',
        category: { name: 'Backend', color: '#2f75a8' },
    },
];

const mountMonth = () => mount(MonthView, {
    props: { monthStart, tasks },
    global: {
        stubs: {
            AppButton: { template: '<button class="add-task-stub"><slot /></button>' },
            Plus: true,
        },
    },
});

describe('MonthView', () => {
    it('selects the day when the cell itself is activated', async () => {
        const wrapper = mountMonth();

        await wrapper.findAll('.month-cell')[0].trigger('keydown.enter');

        expect(wrapper.emitted('select-day')).toHaveLength(1);
    });

    it('does not select the day when a task chip is activated by keyboard', async () => {
        const wrapper = mountMonth();

        // Otherwise Enter on a chip opens the task editor *and* navigates the
        // view underneath it to Day view. Only the navigation is asserted
        // here: the chip's own activation is the browser turning Enter into a
        // click on a native button, which trigger() does not synthesise.
        await wrapper.find('.month-task-chip').trigger('keydown.enter');

        expect(wrapper.emitted('select-day')).toBeUndefined();
    });

    it('does not select the day when the add button is activated by keyboard', async () => {
        const wrapper = mountMonth();

        await wrapper.find('.add-task-stub').trigger('keydown.enter');

        expect(wrapper.emitted('select-day')).toBeUndefined();
    });

    it('does not select the day when a task chip is clicked', async () => {
        const wrapper = mountMonth();

        await wrapper.find('.month-task-chip').trigger('click');

        expect(wrapper.emitted('edit-task')).toHaveLength(1);
        expect(wrapper.emitted('select-day')).toBeUndefined();
    });
});
