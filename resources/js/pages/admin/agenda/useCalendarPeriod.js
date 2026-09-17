import { computed, ref } from 'vue';
import { addDays, addMonths, startOfDay, startOfMonth, startOfWeek } from '../../../shared/planning';
import { lang } from '../../../shared/i18n';

// The three modes that are real stretches of time. Report and Categories are
// not: they hold their own state and fetch on their own.
const CALENDAR_MODES = ['day', 'week', 'month'];

export const isCalendarMode = (mode) => CALENDAR_MODES.includes(mode);

// Start of the period the given date falls in, for a given view mode.
export const periodStartFor = (mode, date) => {
    if (mode === 'week') return startOfWeek(date);
    if (mode === 'month') return startOfMonth(date);

    return startOfDay(date);
};

const capitalize = (value) => value.charAt(0).toUpperCase() + value.slice(1);

/**
 * Which stretch of time the calendar is showing, and how to move it.
 *
 * `referenceDate` anchors whichever view is active: a Monday for week, the 1st
 * for month, the day itself for day. Every move goes through periodStartFor(),
 * so it is never left in a shape another mode does not expect.
 *
 * @param {() => void} onChange  runs after every move, to refetch the range
 */
export function useCalendarPeriod(onChange = () => {}) {
    const viewMode = ref('week');
    const referenceDate = ref(startOfWeek(new Date()));

    const locale = computed(() => (lang.value === 'nl' ? 'nl-NL' : 'en-US'));

    // Month view draws a fixed 6x7 grid starting on the Monday on or before the
    // 1st, so days from the neighbouring months are real, clickable days too.
    // The fetch range is padded to match, or those days would look empty even
    // when they hold tasks.
    const gridStart = computed(() => (viewMode.value === 'month' ? startOfWeek(referenceDate.value) : referenceDate.value));
    const gridEnd = computed(() => {
        if (viewMode.value === 'day') return referenceDate.value;
        if (viewMode.value === 'month') return addDays(gridStart.value, 41);

        return addDays(referenceDate.value, 6);
    });

    // Dutch writes dates in a different order and case from English ("17
    // augustus" against "August 17"). That is correct in each language, but it
    // made this one heading look differently styled depending on the toggle.
    // So the "Weekday, Month Day" pattern is built by hand — only the weekday
    // and month names are translated, not their order — and the heading looks
    // the same in both languages.
    const rangeLabel = computed(() => {
        if (viewMode.value === 'day') {
            const weekday = capitalize(referenceDate.value.toLocaleDateString(locale.value, { weekday: 'long' }));
            const month = capitalize(referenceDate.value.toLocaleDateString(locale.value, { month: 'long' }));

            return `${weekday}, ${month} ${referenceDate.value.getDate()}`;
        }

        if (viewMode.value === 'month') {
            return capitalize(referenceDate.value.toLocaleDateString(locale.value, { month: 'long', year: 'numeric' }));
        }

        const format = (date) => date.toLocaleDateString(locale.value, { day: 'numeric', month: 'short' });

        return `${format(referenceDate.value)} – ${format(addDays(referenceDate.value, 6))}`;
    });

    // True while the view still sits on the period holding today, i.e. the user
    // has not navigated away.
    const isOnCurrentPeriod = () => referenceDate.value.getTime() === periodStartFor(viewMode.value, new Date()).getTime();

    // Every move lands on the start of the period for the current mode.
    const moveTo = (date) => {
        referenceDate.value = periodStartFor(viewMode.value, date);
        onChange();
    };

    const setViewMode = (mode) => {
        if (mode === viewMode.value) {
            return;
        }

        // Report and Categories are not periods, so leave referenceDate alone.
        // Switching back to a calendar tab then lands where the user left it,
        // and neither tab needs the task fetch this would otherwise trigger.
        if (!isCalendarMode(mode)) {
            viewMode.value = mode;

            return;
        }

        // Anchor on today while the user has not navigated away, so each view
        // opens on the current day/week/month. Carrying referenceDate over
        // every time would land Day on the week's Monday instead of today.
        // Once they have moved elsewhere, keep them in that stretch of time.
        const anchor = isOnCurrentPeriod() ? new Date() : referenceDate.value;

        viewMode.value = mode;
        moveTo(anchor);
    };

    // One step in whatever unit the current mode counts in. Stepping keeps the
    // shape it already has — a Monday stays a Monday, the 1st stays the 1st —
    // so it does not need normalising again.
    const step = (direction) => {
        referenceDate.value = viewMode.value === 'month'
            ? addMonths(referenceDate.value, direction)
            : addDays(referenceDate.value, direction * (viewMode.value === 'day' ? 1 : 7));

        onChange();
    };

    const goToPrevious = () => step(-1);
    const goToNext = () => step(1);
    const goToToday = () => moveTo(new Date());

    const selectDay = (date) => {
        viewMode.value = 'day';
        moveTo(date);
    };

    return {
        viewMode,
        referenceDate,
        gridStart,
        gridEnd,
        rangeLabel,
        setViewMode,
        goToPrevious,
        goToNext,
        goToToday,
        selectDay,
    };
}
