<script setup>
import { computed, ref } from 'vue';
import { ChevronLeft, ChevronRight, Plus } from '@lucide/vue';
import DayView from './DayView.vue';
import WeekView from './WeekView.vue';
import MonthView from './MonthView.vue';
import ReportView from './ReportView.vue';
import CategoriesView from './CategoriesView.vue';
import TaskModal from './TaskModal.vue';
import SectionTabs from '../../../components/admin/SectionTabs.vue';
import AppButton from '../../../components/ui/AppButton.vue';
import AppMultiSelect from '../../../components/ui/AppMultiSelect.vue';
import {
    usePlanning,
    startOfWeek,
    startOfMonth,
    startOfDay,
    addDays,
    addMonths,
    TASK_STATUSES,
    taskStatusLabelKey,
} from '../../../shared/planning';
import { copy, lang } from '../../../shared/i18n';
import { useToast } from '../../../shared/toast';
import { useConfirm } from '../../../shared/confirm';

const {
    tasks,
    categories,
    loading,
    fetchTasks,
    fetchCategories,
    createTask,
    updateTask,
    deleteTask,
    startTaskTimer,
    stopTaskTimer,
} = usePlanning();
const toast = useToast();
const { confirm } = useConfirm();

// --- Filters -------------------------------------------------------------

const selectedCategoryIds = ref([]);
const selectedStatuses = ref([]);

// Flat "Parent › Child" list (one level of nesting, same as TaskModal's own
// category select) plus a pseudo-option for tasks with no category at all.
const categoryFilterOptions = computed(() => {
    const options = [{ label: copy('uncategorized'), value: null }];

    for (const category of categories.value) {
        options.push({ label: category.name, value: category.id });

        for (const child of category.children ?? []) {
            options.push({ label: `${category.name} › ${child.name}`, value: child.id });
        }
    }

    return options;
});

const statusFilterOptions = computed(() => TASK_STATUSES.map((status) => ({ label: copy(taskStatusLabelKey[status]), value: status })));

// Tasks are filed against a leaf category ("Learning › Laravel"), so picking
// the parent alone would match nothing. Selecting a parent is taken to mean
// "this and everything under it" — otherwise "Learning" reads as an empty
// category even while its subcategories hold tasks.
const activeCategoryIds = computed(() => {
    const ids = new Set(selectedCategoryIds.value);

    for (const parent of categories.value) {
        if (ids.has(parent.id)) {
            for (const child of parent.children ?? []) {
                ids.add(child.id);
            }
        }
    }

    return ids;
});

// Empty selection = no filter on that facet; both facets AND together.
const filteredTasks = computed(() => tasks.value.filter((task) => {
    const matchesCategory = selectedCategoryIds.value.length === 0 || activeCategoryIds.value.has(task.category_id);
    const matchesStatus = selectedStatuses.value.length === 0 || selectedStatuses.value.includes(task.status);
    return matchesCategory && matchesStatus;
}));

// `referenceDate` anchors whichever view is active: a Monday for week, the
// 1st of the month for month, the exact day for day — each mode normalizes
// it to that shape in setViewMode()/goToToday() so switching modes never
// leaves it in a shape another mode doesn't expect.
const viewMode = ref('week');

// computed (not a plain array) so labels re-render when the admin switches
// their own working language via the header EN/NL toggle.
const viewModeTabs = computed(() => [
    { value: 'day', label: copy('day') },
    { value: 'week', label: copy('week') },
    { value: 'month', label: copy('month') },
    { value: 'categories', label: copy('categories'), right: true },
    { value: 'report', label: copy('report') },
]);
const referenceDate = ref(startOfWeek(new Date()));

const locale = computed(() => (lang.value === 'nl' ? 'nl-NL' : 'en-US'));

// Month view renders a fixed 6x7 grid starting on the Monday on/before the
// 1st, so leading/trailing days from neighboring months are real, clickable
// days too — the fetch range is padded to match, or those days would show
// as empty even when they have tasks.
const gridStart = computed(() => (viewMode.value === 'month' ? startOfWeek(referenceDate.value) : referenceDate.value));
const gridEnd = computed(() => {
    if (viewMode.value === 'day') return referenceDate.value;
    if (viewMode.value === 'month') return addDays(gridStart.value, 41);
    return addDays(referenceDate.value, 6);
});

const capitalize = (value) => value.charAt(0).toUpperCase() + value.slice(1);

// Dutch's natural date order/casing ("17 augustus", lowercase, day before
// month) differs from English's ("August 17", capitalized, month before
// day) — fine normally, but it made this one heading look inconsistently
// styled between languages. Building the "Weekday, Month Day" pattern by
// hand (translating just the weekday/month names, not their order) keeps
// one consistent look in both languages instead of each locale's own convention.
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

const load = () => fetchTasks(gridStart.value, gridEnd.value);

// Start of the period the given date falls in, for a given view mode.
const periodStartFor = (mode, date) => {
    if (mode === 'week') return startOfWeek(date);
    if (mode === 'month') return startOfMonth(date);
    return startOfDay(date);
};

// True while the view still sits on the period containing today — i.e. the
// user hasn't navigated to another day/week/month.
const isOnCurrentPeriod = () => referenceDate.value.getTime() === periodStartFor(viewMode.value, new Date()).getTime();

const setViewMode = (mode) => {
    if (mode === viewMode.value) {
        return;
    }

    // Report and Categories aren't calendar periods — they keep their own
    // state and fetch on their own, so leave referenceDate untouched and
    // switching back to a calendar tab lands where the user left it.
    if (mode === 'report' || mode === 'categories') {
        viewMode.value = mode;
        return;
    }

    // Anchor on today whenever the user hasn't navigated away, so each view
    // opens on the current day/week/month. Carrying referenceDate over
    // unconditionally would land Day on the week's Monday rather than today.
    // Once they have navigated elsewhere, keep them in that stretch of time.
    const anchor = isOnCurrentPeriod() ? new Date() : referenceDate.value;

    if (mode === 'week') {
        referenceDate.value = startOfWeek(anchor);
    } else if (mode === 'month') {
        referenceDate.value = startOfMonth(anchor);
    } else {
        referenceDate.value = startOfDay(anchor);
    }

    viewMode.value = mode;
    load();
};

const goToPrevious = () => {
    if (viewMode.value === 'day') {
        referenceDate.value = addDays(referenceDate.value, -1);
    } else if (viewMode.value === 'month') {
        referenceDate.value = addMonths(referenceDate.value, -1);
    } else {
        referenceDate.value = addDays(referenceDate.value, -7);
    }

    load();
};

const goToNext = () => {
    if (viewMode.value === 'day') {
        referenceDate.value = addDays(referenceDate.value, 1);
    } else if (viewMode.value === 'month') {
        referenceDate.value = addMonths(referenceDate.value, 1);
    } else {
        referenceDate.value = addDays(referenceDate.value, 7);
    }

    load();
};

const goToToday = () => {
    if (viewMode.value === 'week') {
        referenceDate.value = startOfWeek(new Date());
    } else if (viewMode.value === 'month') {
        referenceDate.value = startOfMonth(new Date());
    } else {
        referenceDate.value = startOfDay(new Date());
    }

    load();
};

const onSelectDay = (date) => {
    referenceDate.value = startOfDay(date);
    viewMode.value = 'day';
    load();
};

// --- Create / edit / delete -------------------------------------------

const showModal = ref(false);
const editingTask = ref(null);
const savingTask = ref(false);

const newTaskStart = ref(new Date());

// Each day column/cell has its own small "+" button (see WeekView/DayView/
// MonthView), so the target day is always known up front — use "now"'s
// time-of-day if that's today, otherwise a sensible default of 09:00.
const openCreateModalForDay = (date) => {
    const start = new Date(date);
    const now = new Date();

    if (start.toDateString() === now.toDateString()) {
        start.setHours(now.getHours(), now.getMinutes(), 0, 0);
    } else {
        start.setHours(9, 0, 0, 0);
    }

    editingTask.value = null;
    newTaskStart.value = start;
    showModal.value = true;
};

const openEditModal = (task) => {
    editingTask.value = task;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingTask.value = null;
};

const handleSave = async (payload) => {
    savingTask.value = true;

    try {
        if (editingTask.value) {
            await updateTask(editingTask.value.id, payload);
        } else {
            await createTask(payload);
        }

        closeModal();
        toast.success(copy('taskSaved'));
        await load();
    } catch {
        toast.error(copy('taskError'));
    } finally {
        savingTask.value = false;
    }
};

// No toast on start/stop — it's meant to be a quick, frequent action, and
// the button's own state (icon + ticking elapsed time) is already the
// confirmation. A failure still surfaces one, since that's silent otherwise.
// Both endpoints return the refreshed task; when it's the one currently open
// in the modal, re-point editingTask at it so the modal's timer updates too
// (it reads the task prop, not the in-progress form).
const runTimerAction = async (task, action) => {
    try {
        const updated = await action(task.id);
        await load();

        if (editingTask.value?.id === task.id) {
            editingTask.value = updated;
        }
    } catch {
        toast.error(copy('taskError'));
    }
};

// Categories were edited in the Categories tab — refresh the copy the task
// modal and filters read from, and the tasks whose colours may have changed.
const onCategoriesChanged = async () => {
    await fetchCategories();
    await load();
};

const handleStartTimer = (task) => runTimerAction(task, startTaskTimer);
const handleStopTimer = (task) => runTimerAction(task, stopTaskTimer);

const handleDelete = async () => {
    if (!editingTask.value) {
        return;
    }

    const confirmed = await confirm({
        message: copy('taskDeleteConfirm'),
        confirmLabel: copy('confirmDelete'),
    });

    if (!confirmed) {
        return;
    }

    savingTask.value = true;

    try {
        await deleteTask(editingTask.value.id);
        closeModal();
        toast.success(copy('taskDeleted'));
        await load();
    } catch {
        toast.error(copy('taskError'));
    } finally {
        savingTask.value = false;
    }
};

load();
fetchCategories();
</script>

<template>
    <!-- Passes the layout's flex column through to SectionTabs' panel, which
         grows to fill the page height — without this wrapper joining the
         chain, the agenda's panel would stop at its content. -->
    <div class="flex flex-1 flex-col">
        <SectionTabs :model-value="viewMode" :tabs="viewModeTabs" @update:model-value="setViewMode">
            <!-- Report brings its own period selector and navigation, so none
                 of the calendar chrome below applies to it. -->
            <ReportView v-if="viewMode === 'report'" />

            <!-- Categories manage themselves and reload the shared list, so
                 the calendar picks up new colours/names on the next fetch. -->
            <CategoriesView v-else-if="viewMode === 'categories'" @changed="onCategoriesChanged" />

            <template v-else>
            <!-- One control row for all three calendar views: a single line on
                 md+ (period switcher left, filters right), stacked when
                 narrower. Day additionally gets the add-task button, last on
                 the right — week and month put theirs on each day cell. -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-2">
                    <AppButton variant="icon" :aria-label="copy('previousPeriod')" @click="goToPrevious">
                        <ChevronLeft :size="16" />
                    </AppButton>
                    <button type="button" class="min-w-64 rounded-md px-1 text-center font-serif text-xl leading-tight text-ink transition hover:text-accent" @click="goToToday">
                        {{ rangeLabel }}
                    </button>
                    <AppButton variant="icon" :aria-label="copy('nextPeriod')" @click="goToNext">
                        <ChevronRight :size="16" />
                    </AppButton>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <AppMultiSelect v-model="selectedCategoryIds" class="w-52" :options="categoryFilterOptions" :placeholder="copy('filterByCategory')" />
                    <AppMultiSelect v-model="selectedStatuses" class="w-52" :options="statusFilterOptions" :placeholder="copy('filterByStatus')" />
                    <AppButton v-if="viewMode === 'day'" variant="icon" :aria-label="copy('addTask')" @click="openCreateModalForDay(referenceDate)">
                        <Plus :size="14" />
                    </AppButton>
                </div>
            </div>

            <p v-if="loading" class="admin-note mt-4">{{ copy('loading') }}</p>
            <template v-else>
                <DayView v-if="viewMode === 'day'" class="mt-4" :day="referenceDate" :tasks="filteredTasks" @edit-task="openEditModal" @start-timer="handleStartTimer" @stop-timer="handleStopTimer" />
                <WeekView v-else-if="viewMode === 'week'" class="mt-4" :week-start="referenceDate" :tasks="filteredTasks" @edit-task="openEditModal" @add-task="openCreateModalForDay" @start-timer="handleStartTimer" @stop-timer="handleStopTimer" />
                <MonthView v-else class="mt-4" :month-start="referenceDate" :tasks="filteredTasks" @select-day="onSelectDay" @edit-task="openEditModal" @add-task="openCreateModalForDay" />
            </template>
            </template>
        </SectionTabs>

        <TaskModal
            v-if="showModal"
            :task="editingTask"
            :categories="categories"
            :initial-start="newTaskStart"
            :saving="savingTask"
            @close="closeModal"
            @save="handleSave"
            @delete="handleDelete"
            @start-timer="handleStartTimer"
            @stop-timer="handleStopTimer"
        />
    </div>
</template>
