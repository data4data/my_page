import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { adminUrl } from './admin-path';
import { csrfToken } from './portfolio';

// Monday-based week, matching the backend (Carbon's default startOfWeek()/
// endOfWeek() is Monday-Sunday — see DemoWeekSeeder and ReportController).
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

// Safe against month-end overflow only when `date` is the 1st (which is how
// CalendarView always calls it for month view) — Date.setMonth on the 1st
// can never roll into the following month the way e.g. Jan 31 would.
export const addMonths = (date, amount) => {
    const result = new Date(date);
    result.setMonth(result.getMonth() + amount);
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

// The backend runs on APP_TIMEZONE=UTC and Eloquent serializes datetimes
// with a "Z" suffix, but those values are the admin's own wall-clock time
// (e.g. "09:00" typed into a task), not a real UTC instant. `new Date(iso)`
// would apply a UTC->local conversion and silently shift every displayed
// time by the browser's UTC offset — parse the literal Y-M-D H:i:s digits
// instead so "09:00" always reads back as 09:00, in any timezone.
export const parseServerDatetime = (value) => {
    const match = typeof value === 'string' && value.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2}):(\d{2})/);

    if (!match) {
        return new Date(value);
    }

    const [, year, month, day, hour, minute, second] = match.map(Number);
    return new Date(year, month - 1, day, hour, minute, second);
};

// The counterpart to parseServerDatetime, for the columns that ARE real
// instants rather than wall-clock values: time_logs.started_at/ended_at are
// stamped server-side with Carbon::now() (UTC), so the "Z" Eloquent
// serializes is meaningful and must be honored. Running these through
// parseServerDatetime instead would offset every elapsed time by the
// browser's UTC offset — a stopwatch started "now" would read 2:00:00 in
// UTC+2. Use this for machine-stamped timestamps, that one for typed times.
export const parseServerInstant = (value) => new Date(value);

const pad = (value) => String(value).padStart(2, '0');

// Date -> the "Y-m-d H:i:s" string the Task API expects in request bodies.
export const formatForApi = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;

// Minutes -> "2h 15m" / "45m" / "0m". Reports deal in whole minutes (the
// duration_minutes column), so no seconds component here.
export const formatMinutes = (minutes) => {
    const total = Math.max(0, Math.round(minutes ?? 0));
    const hours = Math.floor(total / 60);
    const rest = total % 60;

    return hours > 0 ? `${hours}h ${rest}m` : `${rest}m`;
};

// The open (not yet stopped) time log on a task, if any.
export const runningTimeLog = (task) => task?.time_logs?.find((log) => !log.ended_at) ?? null;

// Live "M:SS" / "H:MM:SS" label for a running timer, ticking once a second
// and only while one is actually running — most cards never pay the cost,
// and it stops itself when the log closes or the component unmounts.
// Shared by TaskCard and TaskModal so the two can't drift apart.
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

        // started_at is a true UTC instant (server Carbon::now()), unlike the
        // wall-clock start_datetime — so parse it as one.
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

// Shared fetch + reactive slices for the Agenda/Planning Calendar. A fresh
// instance per call (like usePortfolioSource), not a module-level singleton.
export function usePlanning() {
    const tasks = ref([]);
    const categories = ref([]);
    const loading = ref(false);

    const fetchTasks = async (start, end) => {
        loading.value = true;
        const response = await fetch(`${adminUrl('/tasks')}?start=${toDateKey(start)}&end=${toDateKey(end)}`);
        const body = await response.json();
        tasks.value = body.tasks ?? [];
        loading.value = false;
    };

    const fetchCategories = async () => {
        const response = await fetch(adminUrl('/categories'));
        const body = await response.json();
        categories.value = body.categories ?? [];
    };

    // period_start must already be the period's first day — the backend
    // derives period_end from it (see ReportController).
    const fetchReport = async (periodType, periodStart) => {
        const query = new URLSearchParams({ period_type: periodType, period_start: toDateKey(periodStart) });
        const response = await fetch(`${adminUrl('/reports')}?${query}`);

        if (!response.ok) {
            throw new Error('Could not load the report.');
        }

        return response.json();
    };

    const jsonHeaders = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
    };

    const createCategory = async (payload) => {
        const response = await fetch(adminUrl('/categories'), {
            method: 'POST',
            headers: jsonHeaders,
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            throw new Error('Could not create the category.');
        }

        return (await response.json()).category;
    };

    const updateCategory = async (id, payload) => {
        const response = await fetch(`${adminUrl('/categories')}/${id}`, {
            method: 'PUT',
            headers: jsonHeaders,
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            throw new Error('Could not save the category.');
        }

        return (await response.json()).category;
    };

    const deleteCategory = async (id) => {
        const response = await fetch(`${adminUrl('/categories')}/${id}`, {
            method: 'DELETE',
            headers: jsonHeaders,
        });

        if (!response.ok) {
            throw new Error('Could not delete the category.');
        }
    };

    const createTask = async (payload) => {
        const response = await fetch(adminUrl('/tasks'), {
            method: 'POST',
            headers: jsonHeaders,
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            throw new Error('Could not create the task.');
        }

        return (await response.json()).task;
    };

    const updateTask = async (id, payload) => {
        const response = await fetch(`${adminUrl('/tasks')}/${id}`, {
            method: 'PUT',
            headers: jsonHeaders,
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            throw new Error('Could not save the task.');
        }

        return (await response.json()).task;
    };

    const deleteTask = async (id) => {
        const response = await fetch(`${adminUrl('/tasks')}/${id}`, {
            method: 'DELETE',
            headers: jsonHeaders,
        });

        if (!response.ok) {
            throw new Error('Could not delete the task.');
        }
    };

    // period_start/period_end are plain "Y-m-d" strings — the report response
    // hands back exactly the pair the backend derived, so reflections stay
    // keyed to the same period the report summarises.
    const fetchReflection = async (periodType, periodStart, periodEnd) => {
        const query = new URLSearchParams({ period_type: periodType, period_start: periodStart, period_end: periodEnd });
        const response = await fetch(`${adminUrl('/reflections')}?${query}`);

        if (!response.ok) {
            throw new Error('Could not load the reflection.');
        }

        return (await response.json()).reflection;
    };

    const saveReflection = async (payload) => {
        const response = await fetch(adminUrl('/reflections'), {
            method: 'PUT',
            headers: jsonHeaders,
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            throw new Error('Could not save the reflection.');
        }

        return (await response.json()).reflection;
    };

    const startTaskTimer = async (id) => {
        const response = await fetch(`${adminUrl('/tasks')}/${id}/timer/start`, {
            method: 'POST',
            headers: jsonHeaders,
        });

        if (!response.ok) {
            throw new Error('Could not start the timer.');
        }

        return (await response.json()).task;
    };

    const stopTaskTimer = async (id) => {
        const response = await fetch(`${adminUrl('/tasks')}/${id}/timer/stop`, {
            method: 'POST',
            headers: jsonHeaders,
        });

        if (!response.ok) {
            throw new Error('Could not stop the timer.');
        }

        return (await response.json()).task;
    };

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
