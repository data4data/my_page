import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useCalendarPeriod, periodStartFor } from '../agenda/useCalendarPeriod';

// Wed 12 Aug 2026, 14:30. The Monday of that week is the 10th, which is what
// the week view should anchor on.
const NOW = new Date(2026, 7, 12, 14, 30);

const key = (date) => `${date.getFullYear()}-${date.getMonth() + 1}-${date.getDate()}`;

let onChange;

const period = () => useCalendarPeriod(onChange);

beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(NOW);
    onChange = vi.fn();
});

afterEach(() => {
    vi.useRealTimers();
});

describe('useCalendarPeriod', () => {
    it('opens on the Monday of the current week', () => {
        const { viewMode, referenceDate } = period();

        expect(viewMode.value).toBe('week');
        expect(referenceDate.value.getDay()).toBe(1);
        expect(key(referenceDate.value)).toBe('2026-8-10');
    });

    it('covers Monday to Sunday in week mode', () => {
        const { gridStart, gridEnd } = period();

        expect(key(gridStart.value)).toBe('2026-8-10');
        expect(key(gridEnd.value)).toBe('2026-8-16');
    });

    it('covers one day in day mode', () => {
        const { setViewMode, gridStart, gridEnd } = period();

        setViewMode('day');

        expect(key(gridStart.value)).toBe('2026-8-12');
        expect(key(gridEnd.value)).toBe('2026-8-12');
    });

    it('pads the month range out to whole weeks', () => {
        const { setViewMode, referenceDate, gridStart, gridEnd } = period();

        setViewMode('month');

        // The 1st is a Saturday, so the grid starts on the Monday before it
        // and runs the full 6x7 cells.
        expect(key(referenceDate.value)).toBe('2026-8-1');
        expect(gridStart.value.getDay()).toBe(1);
        expect(key(gridStart.value)).toBe('2026-7-27');
        expect(Math.round((gridEnd.value - gridStart.value) / 86400000)).toBe(41);
    });

    it('steps by the unit the current mode counts in', () => {
        const { setViewMode, referenceDate, goToNext, goToPrevious } = period();

        goToNext();
        expect(key(referenceDate.value)).toBe('2026-8-17');

        goToPrevious();
        expect(key(referenceDate.value)).toBe('2026-8-10');

        setViewMode('day');
        goToNext();
        expect(key(referenceDate.value)).toBe('2026-8-13');

        setViewMode('month');
        goToNext();
        expect(key(referenceDate.value)).toBe('2026-9-1');

        goToPrevious();
        expect(key(referenceDate.value)).toBe('2026-8-1');
    });

    it('refetches after every move', () => {
        const { goToNext, goToPrevious, goToToday } = period();

        goToNext();
        goToPrevious();
        goToToday();

        expect(onChange).toHaveBeenCalledTimes(3);
    });

    it('does nothing when the mode is already active', () => {
        const { setViewMode, referenceDate } = period();
        const before = referenceDate.value;

        setViewMode('week');

        expect(referenceDate.value).toBe(before);
        expect(onChange).not.toHaveBeenCalled();
    });

    it('leaves the period alone for Report and Categories', () => {
        const { setViewMode, viewMode, referenceDate } = period();

        setViewMode('report');

        expect(viewMode.value).toBe('report');
        expect(key(referenceDate.value)).toBe('2026-8-10');
        // Neither tab reads the task range, so nothing should be refetched.
        expect(onChange).not.toHaveBeenCalled();
    });

    it('anchors on today when the user has not navigated away', () => {
        const { setViewMode, referenceDate } = period();

        setViewMode('day');

        // Today, not the Monday the week view was sitting on.
        expect(key(referenceDate.value)).toBe('2026-8-12');
    });

    it('keeps the stretch of time the user navigated to', () => {
        const { setViewMode, referenceDate, goToNext } = period();

        goToNext();
        setViewMode('day');

        expect(key(referenceDate.value)).toBe('2026-8-17');
    });

    it('returns to the current period from anywhere', () => {
        const { referenceDate, goToNext, goToToday } = period();

        goToNext();
        goToNext();
        goToToday();

        expect(key(referenceDate.value)).toBe('2026-8-10');
    });

    it('opens the chosen day in day mode with the time stripped', () => {
        const { selectDay, viewMode, referenceDate } = period();

        selectDay(new Date(2026, 7, 20, 16, 45));

        expect(viewMode.value).toBe('day');
        expect(key(referenceDate.value)).toBe('2026-8-20');
        expect(referenceDate.value.getHours()).toBe(0);
        expect(onChange).toHaveBeenCalled();
    });

    it('labels the range the same way in each mode', () => {
        const { setViewMode, rangeLabel } = period();

        expect(rangeLabel.value).toBe('Aug 10 – Aug 16');

        setViewMode('day');
        expect(rangeLabel.value).toBe('Wednesday, August 12');

        setViewMode('month');
        expect(rangeLabel.value).toBe('August 2026');
    });
});

describe('periodStartFor', () => {
    it('gives the start of the period holding the date', () => {
        const wednesday = new Date(2026, 7, 12, 14, 30);

        expect(key(periodStartFor('week', wednesday))).toBe('2026-8-10');
        expect(key(periodStartFor('month', wednesday))).toBe('2026-8-1');
        expect(key(periodStartFor('day', wednesday))).toBe('2026-8-12');
        expect(periodStartFor('day', wednesday).getHours()).toBe(0);
    });
});
