import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

const fetchReport = vi.fn();
const fetchReflection = vi.fn();
const saveReflection = vi.fn();

vi.mock('../../../js/shared/planning', async (importOriginal) => ({
    ...(await importOriginal()),
    usePlanning: () => ({ fetchReport, fetchReflection, saveReflection }),
}));

const ReportView = (await import('../../../js/pages/admin/agenda/ReportView.vue')).default;

const stubs = {
    AppButton: { template: '<button><slot /></button>' },
    AppTextarea: { name: 'AppTextarea', props: ['modelValue'], emits: ['update:modelValue'], template: '<textarea />' },
    AppPillSwitch: { name: 'AppPillSwitch', props: ['modelValue', 'options'], emits: ['update:modelValue'], template: '<div />' },
    ChevronLeft: true,
    ChevronRight: true,
};

const flush = async () => {
    await new Promise((resolve) => setTimeout(resolve, 0));
    await new Promise((resolve) => setTimeout(resolve, 0));
};

const report = (byCategory) => ({
    period_start: '2026-06-08',
    period_end: '2026-06-14',
    totals: { tracked_minutes: 0, planned_minutes: 0 },
    by_status: [],
    by_category: byCategory,
});

const mountView = async (byCategory = []) => {
    fetchReport.mockResolvedValue(report(byCategory));
    fetchReflection.mockResolvedValue({ notes: '' });

    const wrapper = mount(ReportView, { global: { stubs } });
    await flush();

    return wrapper;
};

/**
 * Every one of these numbers can be wrong without anything failing: a division
 * by zero, a bar past 100%, a percentage where nothing was planned.
 */
describe('ReportView: the category bars', () => {
    const rowsOf = (wrapper) => wrapper.vm.categoryRows;

    it('fills the bar by the share of the plan that was done', async () => {
        const wrapper = await mountView([{ category_id: 1, category: 'Work', planned_minutes: 120, minutes: 60 }]);

        expect(rowsOf(wrapper)[0]).toMatchObject({ percent: 50, fillWidth: '50%', overBy: 0, unplanned: false });
    });

    // The bar stops at full; the overshoot is the interesting part.
    it('caps the bar at full while still reporting the overshoot', async () => {
        const wrapper = await mountView([{ category_id: 1, category: 'Work', planned_minutes: 60, minutes: 150 }]);

        expect(rowsOf(wrapper)[0]).toMatchObject({ percent: 250, fillWidth: '100%', overBy: 90 });
    });

    // Nothing planned means a percentage would be a division by zero.
    it('gives no percentage to a category nothing was planned for', async () => {
        const wrapper = await mountView([{ category_id: 1, category: 'Unplanned work', planned_minutes: 0, minutes: 45 }]);

        expect(rowsOf(wrapper)[0]).toMatchObject({ percent: null, fillWidth: '100%', unplanned: true });
    });

    it('leaves an empty row empty rather than full', async () => {
        const wrapper = await mountView([{ category_id: 1, category: 'Idle', planned_minutes: 0, minutes: 0 }]);

        expect(rowsOf(wrapper)[0]).toMatchObject({ percent: null, fillWidth: '0%', unplanned: false });
    });

    // The server groups by id and leaves the label to the client.
    it('names the uncategorized bucket in the reader s language', async () => {
        const wrapper = await mountView([{ category_id: null, category: null, planned_minutes: 30, minutes: 30 }]);

        expect(rowsOf(wrapper)[0].label).toBeTruthy();
        expect(rowsOf(wrapper)[0].label).not.toBe('null');
    });

    it('puts the biggest plan first, and breaks a tie on what was tracked', async () => {
        const wrapper = await mountView([
            { category_id: 1, category: 'Small', planned_minutes: 30, minutes: 30 },
            { category_id: 2, category: 'Tied low', planned_minutes: 60, minutes: 10 },
            { category_id: 3, category: 'Big', planned_minutes: 120, minutes: 10 },
            { category_id: 4, category: 'Tied high', planned_minutes: 60, minutes: 50 },
        ]);

        expect(rowsOf(wrapper).map((row) => row.label))
            .toEqual(['Big', 'Tied high', 'Tied low', 'Small']);
    });

    it('reads a missing figure as nothing rather than breaking', async () => {
        const wrapper = await mountView([{ category_id: 1, category: 'Sparse' }]);

        expect(rowsOf(wrapper)[0]).toMatchObject({ planned: 0, tracked: 0, percent: null });
    });

    it('does not mutate the payload it was given', async () => {
        const rows = [{ category_id: 1, category: 'Work', planned_minutes: 60, minutes: 30 }];
        await mountView(rows);

        expect(rows[0]).toEqual({ category_id: 1, category: 'Work', planned_minutes: 60, minutes: 30 });
    });
});

describe('ReportView: moving between periods', () => {
    it('asks for the period it is showing', async () => {
        await mountView();

        const [type, start] = fetchReport.mock.calls.at(-1);

        expect(type).toBe('week');
        // A Date, not a string, and a Monday: weeks are Monday-based.
        expect(start).toBeInstanceOf(Date);
        expect(start.getDay()).toBe(1);
    });

    // The note reuses the period the backend derived rather than recomputing it.
    it('keys the reflection to the range the report came back with', async () => {
        await mountView();

        expect(fetchReflection).toHaveBeenCalledWith('week', '2026-06-08', '2026-06-14');
    });

    it('shows a failure instead of an empty report', async () => {
        fetchReport.mockRejectedValueOnce(new Error('nope'));
        fetchReflection.mockResolvedValue({ notes: '' });
        vi.spyOn(console, 'error').mockImplementation(() => {});

        const wrapper = mount(ReportView, { global: { stubs } });
        await flush();

        expect(wrapper.vm.failed).toBe(true);
        expect(wrapper.vm.report).toBeNull();

        console.error.mockRestore();
    });
});
