import { describe, expect, it } from 'vitest';
import {
    addDays,
    addMonths,
    formatForApi,
    formatMinutes,
    parseServerDatetime,
    parseServerInstant,
    runningTimeLog,
    startOfDay,
    startOfMonth,
    startOfWeek,
    TASK_STATUSES,
    taskStatusLabelKey,
    toDateKey,
} from '../../js/shared/planning';

/**
 * The half of planning.js that decides what a date means. Everything the
 * calendar draws is built on these, and every one of them can be wrong by a
 * day or an hour without anything failing loudly.
 */
describe('weeks are Monday-based, everywhere', () => {
    // Carbon's default startOfWeek server-side, and firstDayOfWeek: 1 in the
    // PrimeVue config, so the DatePicker agrees.
    it('walks back to Monday from any day of the week', () => {
        const monday = '2026-06-08';

        for (const day of [8, 9, 10, 11, 12, 13, 14]) {
            expect(toDateKey(startOfWeek(new Date(2026, 5, day)))).toBe(monday);
        }
    });

    // The off-by-one that a Sunday-first implementation gets wrong: Sunday
    // belongs to the week that has just ended, not the one starting.
    it('puts Sunday at the end of its week, not the start', () => {
        expect(toDateKey(startOfWeek(new Date(2026, 5, 14)))).toBe('2026-06-08');
        expect(toDateKey(startOfWeek(new Date(2026, 5, 15)))).toBe('2026-06-15');
    });

    it('starts the week at midnight', () => {
        const start = startOfWeek(new Date(2026, 5, 10, 16, 30));

        expect([start.getHours(), start.getMinutes(), start.getSeconds()]).toEqual([0, 0, 0]);
    });
});

describe('moving through the calendar', () => {
    it('crosses a month boundary', () => {
        expect(toDateKey(addDays(new Date(2026, 5, 29), 3))).toBe('2026-07-02');
    });

    it('crosses a year boundary backwards', () => {
        expect(toDateKey(addDays(new Date(2026, 0, 2), -5))).toBe('2025-12-28');
    });

    it('handles a leap day', () => {
        expect(toDateKey(addDays(new Date(2028, 1, 28), 1))).toBe('2028-02-29');
    });

    // setMonth() alone overflows: 31 January plus a month is 31 February,
    // which rolls into March — so a "previous month" step from a 31st would
    // skip February. Clamped, it lands on the last day that exists.
    it('clamps rather than overflowing a short month', () => {
        expect(toDateKey(addMonths(new Date(2026, 0, 31), 1))).toBe('2026-02-28');
        expect(toDateKey(addMonths(new Date(2028, 0, 31), 1))).toBe('2028-02-29');
        expect(toDateKey(addMonths(new Date(2026, 2, 31), -1))).toBe('2026-02-28');
    });

    it('keeps the day when the target month is long enough', () => {
        expect(toDateKey(addMonths(new Date(2026, 0, 15), 1))).toBe('2026-02-15');
        expect(toDateKey(addMonths(new Date(2026, 0, 31), 2))).toBe('2026-03-31');
    });

    it('normalises a day and a month to midnight on the first', () => {
        expect(toDateKey(startOfDay(new Date(2026, 5, 10, 23, 59)))).toBe('2026-06-10');
        expect(toDateKey(startOfMonth(new Date(2026, 5, 10)))).toBe('2026-06-01');
    });
});

/**
 * Not all the datetimes the server sends are real instants, and they
 * serialise identically. Reading one as the other moves every displayed time
 * by the browser's UTC offset — silently, and only for people not on UTC.
 */
describe('wall-clock times versus real instants', () => {
    it('reads a task time as the digits the owner typed', () => {
        const start = parseServerDatetime('2026-06-10T09:00:00.000000Z');

        // 09:00 means 09:00, whatever the browser's offset is.
        expect(start.getHours()).toBe(9);
        expect(start.getMinutes()).toBe(0);
        expect(toDateKey(start)).toBe('2026-06-10');
    });

    it('reads the space-separated form the API also sends', () => {
        expect(parseServerDatetime('2026-06-10 14:45:00').getHours()).toBe(14);
    });

    it('falls back to Date rather than throwing on an unexpected shape', () => {
        expect(Number.isNaN(parseServerDatetime(undefined).getTime())).toBe(true);
        expect(Number.isNaN(parseServerDatetime('not a date').getTime())).toBe(true);
    });

    // time_logs.started_at is stamped by the server, so its Z is meaningful
    // and the browser should shift it.
    it('reads a timer stamp as a genuine instant', () => {
        expect(parseServerInstant('2026-06-10T09:00:00.000000Z').getTime())
            .toBe(Date.UTC(2026, 5, 10, 9, 0, 0));
    });

    it('sends a date back in the shape the API expects', () => {
        expect(formatForApi(new Date(2026, 5, 3, 9, 5, 7))).toBe('2026-06-03 09:05:07');
    });

    // A round trip is what the task modal performs on every save.
    it('survives a round trip unchanged', () => {
        const original = '2026-12-31 23:59:59';

        expect(formatForApi(parseServerDatetime(original))).toBe(original);
    });
});

describe('formatMinutes', () => {
    it.each([
        [0, '0m'],
        [45, '45m'],
        [60, '1h 0m'],
        [135, '2h 15m'],
        [1440, '24h 0m'],
    ])('renders %i minutes as %s', (minutes, expected) => {
        expect(formatMinutes(minutes)).toBe(expected);
    });

    // A missing total is a task nothing has been logged against.
    it('treats nothing, and a negative, as zero', () => {
        expect(formatMinutes(null)).toBe('0m');
        expect(formatMinutes(undefined)).toBe('0m');
        expect(formatMinutes(-30)).toBe('0m');
    });
});

describe('runningTimeLog', () => {
    it('finds the log that has not been stopped', () => {
        const task = {
            time_logs: [
                { id: 1, ended_at: '2026-06-10 10:00:00' },
                { id: 2, ended_at: null },
            ],
        };

        expect(runningTimeLog(task).id).toBe(2);
    });

    it('returns null when everything is closed, or there is nothing at all', () => {
        expect(runningTimeLog({ time_logs: [{ id: 1, ended_at: '2026-06-10 10:00:00' }] })).toBeNull();
        expect(runningTimeLog({ time_logs: [] })).toBeNull();
        expect(runningTimeLog({})).toBeNull();
        expect(runningTimeLog(null)).toBeNull();
    });
});

// TASK_STATUSES mirrors App\Enums\TaskStatus, and a status with no label key
// renders as an empty chip rather than failing.
describe('task statuses', () => {
    it('has a label key for every status', () => {
        for (const status of TASK_STATUSES) {
            expect(taskStatusLabelKey[status], status).toBeTruthy();
        }
    });

    it('lists the five the enum defines, in order', () => {
        expect(TASK_STATUSES).toEqual(['planned', 'in_progress', 'paused', 'done', 'skipped']);
    });
});
