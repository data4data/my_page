<script setup>
import { computed, ref, watch } from 'vue';
import { Play, Square, Trash2, X } from '@lucide/vue';
import AppInput from '../../../components/ui/AppInput.vue';
import AppDatePicker from '../../../components/ui/AppDatePicker.vue';
import AppTextarea from '../../../components/ui/AppTextarea.vue';
import AppSelect from '../../../components/ui/AppSelect.vue';
import AppButton from '../../../components/ui/AppButton.vue';
import { copy } from '../../../shared/i18n';
import {
    TASK_STATUSES,
    taskStatusLabelKey,
    parseServerDatetime,
    formatForApi,
    runningTimeLog,
    useRunningElapsed,
} from '../../../shared/planning';

const props = defineProps({
    task: {
        type: Object,
        default: null, // null = creating a new task
    },
    categories: {
        type: Array,
        required: true,
    },
    initialStart: {
        type: Date,
        required: true,
    },
    saving: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'save', 'delete', 'start-timer', 'stop-timer']);

const isEditing = computed(() => Boolean(props.task));

// Reads straight off the task prop (kept fresh by the parent after each
// start/stop) rather than off the form, so the timer reflects server state
// without the unsaved form fields interfering.
const runningLog = computed(() => runningTimeLog(props.task));
const elapsedLabel = useRunningElapsed(runningLog);

const onTimerClick = () => {
    emit(runningLog.value ? 'stop-timer' : 'start-timer', props.task);
};

const form = ref({
    title: props.task?.title ?? '',
    category_id: props.task?.category_id ?? null,
    status: props.task?.status ?? 'planned',
    // Real Date objects — AppDatePicker binds to them directly.
    start_datetime: props.task ? parseServerDatetime(props.task.start_datetime) : props.initialStart,
    end_datetime: props.task?.end_datetime ? parseServerDatetime(props.task.end_datetime) : null,
    planned_duration_minutes: props.task?.planned_duration_minutes ?? null,
    description: props.task?.description ?? '',
    result_notes: props.task?.result_notes ?? '',
});

// Flat list ("Parent › Child" for one level of nesting) — AppSelect has no
// native optgroup support, and the category tree here is only ever one
// level deep anyway (see Category::parent()/children()).
const categoryOptions = computed(() => {
    const options = [{ label: copy('noCategory'), value: null }];

    for (const category of props.categories) {
        options.push({ label: category.name, value: category.id });

        for (const child of category.children ?? []) {
            options.push({ label: `${category.name} › ${child.name}`, value: child.id });
        }
    }

    return options;
});

const statusOptions = computed(() => TASK_STATUSES.map((status) => ({ label: copy(taskStatusLabelKey[status]), value: status })));

// --- Keeping end time and planned duration in step ------------------------
//
// The two describe the same thing, so letting them drift would leave a task
// whose times say one hour and whose planned duration says ninety minutes —
// and the report trusts the duration, so it would silently contradict the
// calendar. Each edit derives the other.
//
// Driven by watchers on the model rather than DOM @change: `change` only
// fires on blur, so typing a duration and going straight to Save left the
// end time stale. Watching the value catches every edit however it's made,
// including the DatePicker, which emits no DOM change event at all.
//
// flush: 'sync' plus the guard keeps this from ping-ponging — the derived
// write lands while `syncing` is still true, so the paired watcher bails.

const plannedMinutes = () => {
    const value = Number(form.value.planned_duration_minutes);
    return Number.isFinite(value) && value > 0 ? value : null;
};

const syncEndFromDuration = () => {
    const minutes = plannedMinutes();

    if (!form.value.start_datetime || minutes === null) {
        return;
    }

    const end = new Date(form.value.start_datetime);
    end.setMinutes(end.getMinutes() + minutes);
    form.value.end_datetime = end;
};

const syncDurationFromEnd = () => {
    if (!form.value.start_datetime || !form.value.end_datetime) {
        return;
    }

    const spanMs = form.value.end_datetime.getTime() - form.value.start_datetime.getTime();
    form.value.planned_duration_minutes = Math.max(0, Math.round(spanMs / 60000));
};

let syncing = false;

const guarded = (sync) => () => {
    if (syncing) {
        return;
    }

    syncing = true;
    sync();
    syncing = false;
};

watch(() => form.value.planned_duration_minutes, guarded(syncEndFromDuration), { flush: 'sync' });
watch(() => form.value.end_datetime, guarded(syncDurationFromEnd), { flush: 'sync' });
// Moving the start keeps the planned length and carries the end along with it.
watch(() => form.value.start_datetime, guarded(syncEndFromDuration), { flush: 'sync' });

const submit = () => {
    if (!form.value.title.trim() || !form.value.start_datetime) {
        return;
    }

    const payload = {
        title: form.value.title.trim(),
        category_id: form.value.category_id,
        status: form.value.status,
        start_datetime: formatForApi(form.value.start_datetime),
        end_datetime: form.value.end_datetime ? formatForApi(form.value.end_datetime) : null,
        // Number inputs hand back strings; send a real integer (or null when
        // left blank, which lets the report fall back to the start→end span).
        planned_duration_minutes: form.value.planned_duration_minutes === '' || form.value.planned_duration_minutes === null
            ? null
            : Number(form.value.planned_duration_minutes),
        description: form.value.description || null,
        result_notes: form.value.result_notes || null,
    };

    emit('save', payload);
};
</script>

<template>
    <div class="connect-overlay" @click.self="$emit('close')">
        <div class="connect-modal connect-modal-wide">
            <button type="button" class="connect-close" :aria-label="copy('connectClose')" @click="$emit('close')">
                <X :size="18" />
            </button>

            <p class="eyebrow">{{ copy('agenda') }}</p>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-serif text-2xl leading-tight">{{ isEditing ? copy('editTask') : copy('addTask') }}</h2>
                <!-- Only for saved tasks: a timer needs a task id to attach to. -->
                <button
                    v-if="isEditing"
                    type="button"
                    class="timer-button"
                    :class="{ 'timer-button-running': runningLog }"
                    :aria-label="copy(runningLog ? 'stopTimer' : 'startTimer')"
                    @click="onTimerClick"
                >
                    <component :is="runningLog ? Square : Play" :size="11" />
                    <span>{{ elapsedLabel ?? copy('startTimer') }}</span>
                </button>
            </div>

            <form class="admin-grid mt-6" novalidate @submit.prevent="submit">
                <label>
                    {{ copy('taskCategory') }}
                    <AppSelect v-model="form.category_id" :options="categoryOptions" />
                </label>

                <label>
                    {{ copy('taskStatus') }}
                    <AppSelect v-model="form.status" :options="statusOptions" />
                </label>

                <label>
                    {{ copy('taskStart') }}
                    <AppDatePicker v-model="form.start_datetime" show-time />
                </label>

                <label>
                    {{ copy('taskEnd') }}
                    <AppDatePicker v-model="form.end_datetime" show-time />
                </label>

                <label class="admin-full">
                    {{ copy('taskPlannedDuration') }}
                    <AppInput v-model="form.planned_duration_minutes" type="number" min="0" step="5" />
                    <span class="mt-1 block text-xs leading-5 text-taupe">{{ copy('taskPlannedDurationHint') }}</span>
                </label>

                <label class="admin-full">
                    {{ copy('taskTitle') }}
                    <AppInput v-model="form.title" required />
                </label>

                <label class="admin-full">
                    {{ copy('taskDescription') }}
                    <AppTextarea v-model="form.description" rows="2" />
                </label>

                <label class="admin-full">
                    {{ copy('taskResultNotes') }}
                    <AppTextarea v-model="form.result_notes" rows="3" />
                </label>

                <div class="admin-full mt-2 flex items-center justify-between gap-3">
                    <AppButton v-if="isEditing" type="button" variant="icon-danger" :aria-label="copy('taskDelete')" :disabled="saving" @click="$emit('delete')">
                        <Trash2 :size="16" />
                    </AppButton>
                    <span v-else></span>

                    <div class="flex items-center gap-2">
                        <AppButton type="button" variant="secondary" size="sm" @click="$emit('close')">{{ copy('taskCancel') }}</AppButton>
                        <AppButton type="submit" variant="accent" size="sm" :disabled="saving">
                            {{ saving ? copy('saving') : copy('taskSave') }}
                        </AppButton>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
