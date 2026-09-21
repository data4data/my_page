import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import TaskCard from '../../../../../js/pages/admin/agenda/components/TaskCard.vue';
import { TASK_STATUSES } from '../../../../../js/shared/planning';

const task = {
    id: 1,
    title: 'Write the migration',
    status: 'planned',
    start_datetime: '2026-08-21 09:00:00',
    end_datetime: '2026-08-21 10:30:00',
    category: { name: 'Backend', color: '#2f75a8', icon: null },
    running_log: null,
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

        // @click.stop guards the mouse path only: a bubbling keydown would both
        // toggle the timer and open the modal.
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
        const wrapper = mountCard({ running_log: { id: 9, started_at: '2026-08-21T09:00:00Z' } });

        await wrapper.find('.timer-button').trigger('click');

        expect(wrapper.emitted('stop-timer')).toHaveLength(1);
        expect(wrapper.emitted('start-timer')).toBeUndefined();
    });
});

describe('TaskCard status', () => {
    // A --status-* trio hangs off this class, and the report's chips read it too.
    it('carries the status class the palette hangs off', () => {
        for (const status of TASK_STATUSES) {
            expect(mountCard({ status }).find('.task-card').classes()).toContain(`status-${status}`);
        }
    });

    // Colour repeats the label; it never replaces it.
    it('still names the status in text', () => {
        const wrapper = mountCard({ status: 'done' });

        expect(wrapper.find('.task-card-status').text()).not.toBe('');
    });

    // A week column is a seventh of the sheet, and both children are flex-none.
    it('lets the time and badge row wrap', () => {
        const css = readFileSync(join(process.cwd(), 'resources/css/agenda.css'), 'utf8');
        const rule = css.match(/\.task-card-meta\s*\{[^}]*\}/);

        expect(rule, '.task-card-meta should be declared in agenda.css').not.toBeNull();
        expect(rule[0]).toMatch(/flex-wrap/);
    });
});
