<script setup>
import { computed, ref } from 'vue';
import { ChevronLeft, ChevronRight, Plus } from '@lucide/vue';
import DayView from './DayView.vue';
import WeekView from './WeekView.vue';
import MonthView from './MonthView.vue';
import ReportView from './ReportView.vue';
import CategoriesView from './CategoriesView.vue';
import TaskModal from './TaskModal.vue';
import AdminSheet from '../../../components/admin/AdminSheet.vue';
import AppButton from '../../../components/ui/AppButton.vue';
import AppMultiSelect from '../../../components/ui/AppMultiSelect.vue';
import { usePlanning } from '../../../shared/planning';
import { useCalendarPeriod } from './useCalendarPeriod';
import { useTaskFilters } from './useTaskFilters';
import { copy } from '../../../shared/i18n';
import { reportError } from '../../../shared/api';
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

const {
    selectedCategoryIds,
    selectedStatuses,
    categoryFilterOptions,
    statusFilterOptions,
    filteredTasks,
} = useTaskFilters(tasks, categories);

// An arrow, not load() directly: load() is declared below and reads
// gridStart/gridEnd back out of this same call.
const {
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
} = useCalendarPeriod(() => load());

const load = () => fetchTasks(gridStart.value, gridEnd.value);

// computed, so the labels re-render on the EN/NL toggle.
const viewModeTabs = computed(() => [
    { value: 'day', label: copy('day') },
    { value: 'week', label: copy('week') },
    { value: 'month', label: copy('month') },
    { value: 'categories', label: copy('categories') },
    { value: 'report', label: copy('report') },
]);

// The calendar modes name the period on screen; the other two have none.
const subtitle = computed(() => {
    if (viewMode.value === 'categories') {
        return copy('subtitleAgendaCategories');
    }

    return viewMode.value === 'report' ? '' : rangeLabel.value;
});

// Only one timer runs at a time, so the in-progress task is the running one
// by definition — no separate state to disagree with the list.
const runningTask = computed(() => tasks.value.find((task) => task.status === 'in_progress'));

const status = computed(() => (runningTask.value ? `${copy('timerRunning')} — ${runningTask.value.title}` : ''));

const showModal = ref(false);
const editingTask = ref(null);
const savingTask = ref(false);

const newTaskStart = ref(new Date());

// The target day comes from the cell's own "+" button; the time is now if that
// is today, and 09:00 otherwise.
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
    } catch (error) {
        reportError(error, copy('taskError'));
    } finally {
        savingTask.value = false;
    }
};

// No toast on success: the button's own ticking state is the confirmation.
// The refreshed task is re-pointed at the open modal, which reads the prop.
const runTimerAction = async (task, action) => {
    try {
        const updated = await action(task.id);
        await load();

        if (editingTask.value?.id === task.id) {
            editingTask.value = updated;
        }
    } catch (error) {
        reportError(error, copy('taskError'));
    }
};

// Refresh both: the filters read the categories, the tasks carry their colours.
const onCategoriesChanged = async () => {
    try {
        await fetchCategories();
        await load();
    } catch (error) {
        reportError(error, copy('taskLoadError'));
    }
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
    } catch (error) {
        reportError(error, copy('taskError'));
    } finally {
        savingTask.value = false;
    }
};

// Caught, not floating: a rejected promise nobody handles says nothing at all.
load().catch((error) => reportError(error, copy('taskLoadError')));
fetchCategories().catch((error) => reportError(error, copy('categoryLoadError')));
</script>

<template>
    <AdminSheet
        :model-value="viewMode"
        :tabs="viewModeTabs"
        :title="copy('agenda')"
        :subtitle="subtitle"
        @update:model-value="setViewMode"
    >
        <!-- Report brings its own period selector, so no calendar chrome. -->
        <ReportView v-if="viewMode === 'report'" />

        <CategoriesView v-else-if="viewMode === 'categories'" @changed="onCategoriesChanged" />

        <template v-else>
            <div class="admin-toolbar">
                <div class="period-nav">
                    <button type="button" class="period-nav-button" :aria-label="copy('previousPeriod')" @click="goToPrevious">
                        <ChevronLeft :size="15" aria-hidden="true" />
                    </button>
                    <button type="button" class="period-nav-label" @click="goToToday">{{ rangeLabel }}</button>
                    <button type="button" class="period-nav-button" :aria-label="copy('nextPeriod')" @click="goToNext">
                        <ChevronRight :size="15" aria-hidden="true" />
                    </button>
                </div>

                <div class="admin-toolbar-end">
                    <AppMultiSelect v-model="selectedCategoryIds" class="w-52" :options="categoryFilterOptions" :placeholder="copy('filterByCategory')" />
                    <AppMultiSelect v-model="selectedStatuses" class="w-52" :options="statusFilterOptions" :placeholder="copy('filterByStatus')" />
                </div>
            </div>

            <p v-if="loading" class="admin-note">{{ copy('loading') }}</p>
            <template v-else>
                <DayView v-if="viewMode === 'day'" :day="referenceDate" :tasks="filteredTasks" @edit-task="openEditModal" @start-timer="handleStartTimer" @stop-timer="handleStopTimer" />
                <WeekView v-else-if="viewMode === 'week'" :week-start="referenceDate" :tasks="filteredTasks" @edit-task="openEditModal" @add-task="openCreateModalForDay" @start-timer="handleStartTimer" @stop-timer="handleStopTimer" />
                <MonthView v-else :month-start="referenceDate" :tasks="filteredTasks" @select-day="selectDay" @edit-task="openEditModal" @add-task="openCreateModalForDay" />
            </template>
        </template>

        <template v-if="status" #status>{{ status }}</template>

        <!-- Day view has no cell to hang an add button off, so it lives here. -->
        <template v-if="viewMode === 'day'" #actions>
            <AppButton variant="solid" @click="openCreateModalForDay(referenceDate)">
                <Plus :size="14" aria-hidden="true" />
                {{ copy('addTask') }}
            </AppButton>
        </template>
    </AdminSheet>

    <!-- Sibling of the sheet, not inside it: the modal is the page's overlay
         and must not inherit the sheet's overflow or stacking. -->
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
</template>
