import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import TaskModal from '../../../js/pages/admin/agenda/TaskModal.vue';

const stubs = {
    AdminModal: { template: '<div><slot name="head-aside" /><slot /><slot name="footer" /></div>' },
    AppInput: {
        name: 'AppInput',
        props: ['modelValue'],
        emits: ['update:modelValue'],
        template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    },
    AppTextarea: { name: 'AppTextarea', props: ['modelValue'], emits: ['update:modelValue'], template: '<textarea />' },
    AppSelect: { name: 'AppSelect', props: ['modelValue', 'options'], emits: ['update:modelValue'], template: '<select />' },
    AppDatePicker: { name: 'AppDatePicker', props: ['modelValue'], emits: ['update:modelValue'], template: '<input type="datetime-local" />' },
    AppButton: { template: '<button><slot /></button>' },
    Play: true,
    Square: true,
    Trash2: true,
};

const initialStart = new Date(2026, 5, 10, 9, 0, 0);

const mountModal = (props = {}) => mount(TaskModal, {
    props: { categories: [], initialStart, ...props },
    global: { stubs },
});

const fieldNamed = (wrapper, name) => wrapper.findAllComponents({ name })[0];
const datePickers = (wrapper) => wrapper.findAllComponents({ name: 'AppDatePicker' });

describe('TaskModal: keeping end time and planned duration in step', () => {
    // The report trusts the duration, so it must not contradict the times.
    it('derives the end time when a duration is typed', async () => {
        const wrapper = mountModal();

        await fieldNamed(wrapper, 'AppInput').vm.$emit('update:modelValue', '90');

        // The number input is the planned duration; the end picker follows.
        expect(datePickers(wrapper)[1].props('modelValue')).toEqual(new Date(2026, 5, 10, 10, 30, 0));
    });

    it('derives the duration when an end time is picked', async () => {
        const wrapper = mountModal();

        await datePickers(wrapper)[1].vm.$emit('update:modelValue', new Date(2026, 5, 10, 11, 15, 0));

        expect(wrapper.vm.form.planned_duration_minutes).toBe(135);
    });

    // Without the guard the two watchers would write to each other forever.
    it('does not ping-pong between the two', async () => {
        const wrapper = mountModal();

        await fieldNamed(wrapper, 'AppInput').vm.$emit('update:modelValue', '60');

        expect(wrapper.vm.form.planned_duration_minutes).toBe('60');
        expect(wrapper.vm.form.end_datetime).toEqual(new Date(2026, 5, 10, 10, 0, 0));
    });

    it('carries the end along when the start moves, keeping the length', async () => {
        const wrapper = mountModal();

        await fieldNamed(wrapper, 'AppInput').vm.$emit('update:modelValue', '30');
        await datePickers(wrapper)[0].vm.$emit('update:modelValue', new Date(2026, 5, 11, 14, 0, 0));

        expect(datePickers(wrapper)[1].props('modelValue')).toEqual(new Date(2026, 5, 11, 14, 30, 0));
    });

    it('leaves an open-ended task open-ended', async () => {
        const wrapper = mountModal();

        await fieldNamed(wrapper, 'AppInput').vm.$emit('update:modelValue', '');

        expect(wrapper.vm.form.end_datetime).toBeNull();
    });
});

describe('TaskModal: what it submits', () => {
    const submit = (wrapper) => wrapper.find('form').trigger('submit');

    it('sends wall-clock strings, not ISO instants', async () => {
        const wrapper = mountModal();

        await fieldNamed(wrapper, 'AppInput').vm.$emit('update:modelValue', '60');
        wrapper.vm.form.title = 'Focus block';
        await submit(wrapper);

        const [payload] = wrapper.emitted('save')[0];

        // "09:00" as typed. An ISO string would shift by the browser offset.
        expect(payload.start_datetime).toBe('2026-06-10 09:00:00');
        expect(payload.end_datetime).toBe('2026-06-10 10:00:00');
    });

    // Blank is null, so the report falls back to the start→end span.
    it('sends the duration as a number, and blank as null', async () => {
        const wrapper = mountModal();

        wrapper.vm.form.title = 'Task';
        await fieldNamed(wrapper, 'AppInput').vm.$emit('update:modelValue', '45');
        await submit(wrapper);

        expect(wrapper.emitted('save')[0][0].planned_duration_minutes).toBe(45);

        const blank = mountModal();
        blank.vm.form.title = 'Task';
        blank.vm.form.planned_duration_minutes = '';
        await submit(blank);

        expect(blank.emitted('save')[0][0].planned_duration_minutes).toBeNull();
    });

    it('refuses to submit without a title', async () => {
        const wrapper = mountModal();

        await submit(wrapper);

        expect(wrapper.emitted('save')).toBeUndefined();
    });

    it('trims the title, so a space is not a name', async () => {
        const wrapper = mountModal();

        wrapper.vm.form.title = '  Spaced  ';
        await submit(wrapper);

        expect(wrapper.emitted('save')[0][0].title).toBe('Spaced');
    });

    it('sends empty free text as null rather than an empty string', async () => {
        const wrapper = mountModal();

        wrapper.vm.form.title = 'Task';
        await submit(wrapper);

        const [payload] = wrapper.emitted('save')[0];

        expect(payload.description).toBeNull();
        expect(payload.result_notes).toBeNull();
    });
});

describe('TaskModal: the category list', () => {
    // AppSelect has no optgroup, and the tree is only ever one level deep.
    it('flattens one level of nesting into "Parent › Child"', () => {
        const wrapper = mountModal({
            categories: [
                { id: 1, name: 'Work', children: [{ id: 2, name: 'Admin' }] },
                { id: 3, name: 'Home', children: [] },
            ],
        });

        const labels = fieldNamed(wrapper, 'AppSelect').props('options').map((o) => o.label);

        expect(labels).toEqual([expect.any(String), 'Work', 'Work › Admin', 'Home']);
    });

    it('offers "no category" first, so a task need not have one', () => {
        expect(fieldNamed(mountModal(), 'AppSelect').props('options')[0].value).toBeNull();
    });
});

describe('TaskModal: the timer', () => {
    // A timer needs a task id to attach to, so a new task has no button.
    it('appears only for a task that has been saved', () => {
        expect(mountModal().find('.timer-button').exists()).toBe(false);

        const saved = mountModal({ task: { id: 7, title: 'Saved', start_datetime: '2026-06-10 09:00:00', running_log: null } });

        expect(saved.find('.timer-button').exists()).toBe(true);
    });

    it('asks to stop while one is running, and to start otherwise', async () => {
        const running = mountModal({
            task: {
                id: 7,
                title: 'Running',
                start_datetime: '2026-06-10 09:00:00',
                running_log: { id: 1, started_at: new Date().toISOString() },
            },
        });

        await running.find('.timer-button').trigger('click');
        expect(running.emitted('stop-timer')).toHaveLength(1);

        const idle = mountModal({ task: { id: 8, title: 'Idle', start_datetime: '2026-06-10 09:00:00', running_log: null } });

        await idle.find('.timer-button').trigger('click');
        expect(idle.emitted('start-timer')).toHaveLength(1);
    });
});
