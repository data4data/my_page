import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { adminUrl } from './admin-path';
import { apiFetch } from './api';

// Monday-based, matching Carbon's default on the backend.
export const startOfWeek = (date) => {
    const result = new Date(date);
    const day = result.getDay(); // 0 = Sunday .. 6 = Saturday
    const diff = day === 0 ? -6 : 1 - day;
    result.setDate(result.getDate() + diff);
    result.setHours(0, 0, 0, 0);
    return result;
};

export const addDays = (date, amount) => {
    const result = new Date(date);
    result.setDate(result.getDate() + amount);
    return result;
};

// Clamped to the last day of the target month. setMonth() alone overflows —
// 31 January plus a month is 31 February, which rolls into March — so a
// "previous month" step from a 31st would skip February entirely.
export const addMonths = (date, amount) => {
    const result = new Date(date);
    const day = result.getDate();

    result.setDate(1);
    result.setMonth(result.getMonth() + amount);

    const lastDayOfTarget = new Date(result.getFullYear(), result.getMonth() + 1, 0).getDate();
    result.setDate(Math.min(day, lastDayOfTarget));

    return result;
};

export const startOfDay = (date) => {
    const result = new Date(date);
    result.setHours(0, 0, 0, 0);
    return result;
};

export const startOfMonth = (date) => {
    const result = new Date(date.getFullYear(), date.getMonth(), 1);
    result.setHours(0, 0, 0, 0);
    return result;
};

export const toDateKey = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// Wall-clock time ("09:00" as typed), not an instant, despite the "Z". A plain
// new Date() would shift every displayed time by the browser's offset.
export const parseServerDatetime = (value) => {
    const match = typeof value === 'string' && value.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2}):(\d{2})/);

    if (!match) {
        return new Date(value);
    }

    const [, year, month, day, hour, minute, second] = match.map(Number);
    return new Date(year, month - 1, day, hour, minute, second);
};

// For the columns that are real instants: time_logs is stamped server-side.
export const parseServerInstant = (value) => new Date(value);

const pad = (value) => String(value).padStart(2, '0');

// Date -> the "Y-m-d H:i:s" string the Task API expects.
export const formatForApi = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;

// Minutes -> "2h 15m" / "45m" / "0m".
export const formatMinutes = (minutes) => {
    const total = Math.max(0, Math.round(minutes ?? 0));
    const hours = Math.floor(total / 60);
    const rest = total % 60;

    return hours > 0 ? `${hours}h ${rest}m` : `${rest}m`;
};

// The open (not yet stopped) time log on a task, if any.
export const runningTimeLog = (task) => task?.time_logs?.find((log) => !log.ended_at) ?? null;

// Live "M:SS" / "H:MM:SS" label for a running timer. Shared by TaskCard and
// TaskModal, so the two cannot drift.
export function useRunningElapsed(runningLog) {
    const now = ref(new Date());
    let intervalId = null;

    const stopTicking = () => {
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }
    };

    watch(runningLog, (log) => {
        stopTicking();

        if (log) {
            now.value = new Date();
            intervalId = setInterval(() => { now.value = new Date(); }, 1000);
        }
    }, { immediate: true });

    onBeforeUnmount(stopTicking);

    return computed(() => {
        if (!runningLog.value) {
            return null;
        }

        // A real instant, unlike the wall-clock start_datetime.
        const started = parseServerInstant(runningLog.value.started_at);
        const totalSeconds = Math.max(0, Math.floor((now.value - started) / 1000));
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;
        const pad = (value) => String(value).padStart(2, '0');

        return hours > 0 ? `${hours}:${pad(minutes)}:${pad(seconds)}` : `${minutes}:${pad(seconds)}`;
    });
}

// Mirrors App\Enums\TaskStatus — keep the two in step.
export const TASK_STATUSES = ['planned', 'in_progress', 'paused', 'done', 'skipped'];

export const taskStatusLabelKey = {
    planned: 'taskStatusPlanned',
    in_progress: 'taskStatusInProgress',
    paused: 'taskStatusPaused',
    done: 'taskStatusDone',
    skipped: 'taskStatusSkipped',
};

/*
 * None of these carry error text. What a person is told is decided by the
 * component that called them, in the language on screen — see errorMessage()
 * in api.js.
 */
// A fresh instance per call, not a module-level singleton.
export function usePlanning() {
    const tasks = ref([]);
    const categories = ref([]);
    const loading = ref(false);

    const fetchTasks = async (start, end) => {
        loading.value = true;

        try {
            const query = new URLSearchParams({ start: toDateKey(start), end: toDateKey(end) });
            const body = await apiFetch(`${adminUrl('/tasks')}?${query}`);
            tasks.value = body.tasks ?? [];
        } finally {
            // A failed load must still clear the flag.
            loading.value = false;
        }
    };

    const fetchCategories = async () => {
        const body = await apiFetch(adminUrl('/categories'));
        categories.value = body.categories ?? [];
    };

    // period_start must already be the period's first day; the backend derives
    // the end from it.
    const fetchReport = (periodType, periodStart) => {
        const query = new URLSearchParams({ period_type: periodType, period_start: toDateKey(periodStart) });

        return apiFetch(`${adminUrl('/reports')}?${query}`);
    };

    const createCategory = async (payload) => (await apiFetch(adminUrl('/categories'), {
        method: 'POST',
        body: payload,
    })).category;

    const updateCategory = async (id, payload) => (await apiFetch(`${adminUrl('/categories')}/${id}`, {
        method: 'PUT',
        body: payload,
    })).category;

    const deleteCategory = (id) => apiFetch(`${adminUrl('/categories')}/${id}`, { method: 'DELETE' });

    const createTask = async (payload) => (await apiFetch(adminUrl('/tasks'), {
        method: 'POST',
        body: payload,
    })).task;

    const updateTask = async (id, payload) => (await apiFetch(`${adminUrl('/tasks')}/${id}`, {
        method: 'PUT',
        body: payload,
    })).task;

    const deleteTask = (id) => apiFetch(`${adminUrl('/tasks')}/${id}`, { method: 'DELETE' });

    // Keyed on the start alone: the backend derives the end from it, and
    // normalises the start to the period's first day.
    const fetchReflection = async (periodType, periodStart) => {
        const query = new URLSearchParams({ period_type: periodType, period_start: periodStart });

        return (await apiFetch(`${adminUrl('/reflections')}?${query}`)).reflection;
    };

    const saveReflection = async (payload) => (await apiFetch(adminUrl('/reflections'), {
        method: 'PUT',
        body: payload,
    })).reflection;

    const startTaskTimer = async (id) => (await apiFetch(`${adminUrl('/tasks')}/${id}/timer/start`, {
        method: 'POST',
    })).task;

    const stopTaskTimer = async (id) => (await apiFetch(`${adminUrl('/tasks')}/${id}/timer/stop`, {
        method: 'POST',
    })).task;

    return {
        tasks,
        categories,
        loading,
        fetchTasks,
        fetchCategories,
        fetchReport,
        fetchReflection,
        saveReflection,
        createCategory,
        updateCategory,
        deleteCategory,
        createTask,
        updateTask,
        deleteTask,
        startTaskTimer,
        stopTaskTimer,
    };
}
