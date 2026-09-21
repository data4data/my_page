import { computed, ref } from 'vue';
import { addDays, addMonths, startOfDay, startOfMonth, startOfWeek } from '../../../../shared/planning';
import { lang } from '../../../../shared/i18n';

// The modes that are real stretches of time. Report is not.
const CALENDAR_MODES = ['day', 'week', 'month'];

export const isCalendarMode = (mode) => CALENDAR_MODES.includes(mode);

export const periodStartFor = (mode, date) => {
    if (mode === 'week') return startOfWeek(date);
    if (mode === 'month') return startOfMonth(date);

    return startOfDay(date);
};

const capitalize = (value) => value.charAt(0).toUpperCase() + value.slice(1);

/**
 * Which stretch of time the calendar is showing, and how to move it.
 * `referenceDate` is a Monday for week, the 1st for month, the day itself for
 * day — every move goes through periodStartFor() to keep it that way.
 *
 * @param {() => void} onChange  runs after every move, to refetch the range
 */
export function useCalendarPeriod(onChange = () => {}) {
    const viewMode = ref('week');
    const referenceDate = ref(startOfWeek(new Date()));

    const locale = computed(() => (lang.value === 'nl' ? 'nl-NL' : 'en-US'));

    // Month draws a fixed 6x7 grid, so the fetch range is padded to match or
    // the neighbouring months' days look empty when they hold tasks.
    const gridStart = computed(() => (viewMode.value === 'month' ? startOfWeek(referenceDate.value) : referenceDate.value));
    const gridEnd = computed(() => {
        if (viewMode.value === 'day') return referenceDate.value;
        if (viewMode.value === 'month') return addDays(gridStart.value, 41);

        return addDays(referenceDate.value, 6);
    });

    // The "Weekday, Month Day" pattern is built by hand so the heading looks
    // the same in both languages: only the names are translated, not the order.
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

    // True while the view still sits on the period holding today.
    const isOnCurrentPeriod = () => referenceDate.value.getTime() === periodStartFor(viewMode.value, new Date()).getTime();

    const moveTo = (date) => {
        referenceDate.value = periodStartFor(viewMode.value, date);
        onChange();
    };

    const setViewMode = (mode) => {
        if (mode === viewMode.value) {
            return;
        }

        // Not a period: leaving referenceDate alone means switching back lands
        // where the user left it, and the report does not need the task fetch.
        if (!isCalendarMode(mode)) {
            viewMode.value = mode;

            return;
        }

        // Anchor on today until the user navigates away, or switching to Day
        // would land on the week's Monday instead of today.
        const anchor = isOnCurrentPeriod() ? new Date() : referenceDate.value;

        viewMode.value = mode;
        moveTo(anchor);
    };

    // Stepping keeps the shape it has — a Monday stays a Monday — so it needs
    // no normalising.
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
