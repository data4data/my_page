import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import TaskCard from './TaskCard.vue';

const task = {
    id: 1,
    title: 'Write the migration',
    status: 'planned',
    start_datetime: '2026-08-21 09:00:00',
    end_datetime: '2026-08-21 10:30:00',
    category: { name: 'Backend', color: '#2f75a8', icon: null },
    time_logs: [],
};

const mountCard = (overrides = {}) => mount(TaskCard, {
    props: { task: { ...task, ...overrides } },
});

describe('TaskCard', () => {
    it('opens the editor when the card itself is activated', async () => {
        const wrapper = mountCard();

        await wrapper.find('.task-card').trigger('keydown.enter');

        expect(wrapper.emitted('edit')).toHaveLength(1);
    });

    it('does not open the editor when the timer is activated by keyboard', async () => {
        const wrapper = mountCard();

        // @click.stop guards the mouse path but not the keyboard one: keydown
        // still bubbles to the card, so Enter would both toggle the timer and
        // open the modal on top of it.
        await wrapper.find('.timer-button').trigger('keydown.enter');

        expect(wrapper.emitted('edit')).toBeUndefined();
    });

    it('does not open the editor when the timer is clicked', async () => {
        const wrapper = mountCard();

        await wrapper.find('.timer-button').trigger('click');

        expect(wrapper.emitted('edit')).toBeUndefined();
    });

    it('starts the timer when none is running', async () => {
        const wrapper = mountCard();

        await wrapper.find('.timer-button').trigger('click');

        expect(wrapper.emitted('start-timer')).toHaveLength(1);
        expect(wrapper.emitted('stop-timer')).toBeUndefined();
    });

    it('stops the timer when one is already running', async () => {
        const wrapper = mountCard({ time_logs: [{ id: 9, started_at: '2026-08-21T09:00:00Z', ended_at: null }] });

        await wrapper.find('.timer-button').trigger('click');

        expect(wrapper.emitted('stop-timer')).toHaveLength(1);
        expect(wrapper.emitted('start-timer')).toBeUndefined();
    });
});
