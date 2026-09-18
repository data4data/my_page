<script setup>
import { computed } from 'vue';
import { Play, Square } from '@lucide/vue';
import { iconMap } from '../../../shared/icons';
import { copy } from '../../../shared/i18n';
import { parseServerDatetime, runningTimeLog, taskStatusLabelKey, useRunningElapsed } from '../../../shared/planning';

const props = defineProps({
    task: {
        type: Object,
        required: true,
    },
    // 'row' across the full sheet (day view), 'stack' inside a week column.
    // The content is identical; only the axis differs.
    layout: {
        type: String,
        default: 'stack',
    },
});

const emit = defineEmits(['edit', 'start-timer', 'stop-timer']);

const categoryColor = computed(() => props.task.category?.color ?? 'var(--color-steel)');
const categoryIcon = computed(() => (props.task.category?.icon ? iconMap[props.task.category.icon] : null));
const statusLabel = computed(() => copy(taskStatusLabelKey[props.task.status] ?? 'taskStatusPlanned'));
const categoryLabel = computed(() => props.task.category?.name ?? copy('uncategorized'));

const timeRange = computed(() => {
    const start = parseServerDatetime(props.task.start_datetime);
    const formatTime = (date) => date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });

    if (!props.task.end_datetime) {
        return formatTime(start);
    }

    return `${formatTime(start)}–${formatTime(parseServerDatetime(props.task.end_datetime))}`;
});

// --- Timer ----------------------------------------------------------------

const runningLog = computed(() => runningTimeLog(props.task));
const elapsedLabel = useRunningElapsed(runningLog);

const onTimerClick = () => {
    emit(runningLog.value ? 'stop-timer' : 'start-timer', props.task);
};
</script>

<template>
    <div
        role="button"
        tabindex="0"
        class="task-card"
        :class="[`status-${task.status}`, `task-card-${layout}`]"
        :style="{ '--task-color': categoryColor }"
        @click="emit('edit', task)"
        @keydown.enter="emit('edit', task)"
    >
        <template v-if="layout === 'row'">
            <span class="task-card-time w-24">{{ timeRange }}</span>

            <span class="task-card-main">
                <span class="task-card-title">{{ task.title }}</span>
                <span class="task-card-category">
                    <component :is="categoryIcon" v-if="categoryIcon" :size="12" aria-hidden="true" class="inline-block align-[-1px]" />
                    {{ categoryLabel }}
                </span>
            </span>

            <span class="task-card-status">{{ statusLabel }}</span>
        </template>

        <template v-else>
            <span class="task-card-title">{{ task.title }}</span>

            <span class="task-card-meta">
                <span class="task-card-time">{{ timeRange }}</span>
                <span class="task-card-status ml-auto">{{ statusLabel }}</span>
            </span>
        </template>

        <!-- Shared by both layouts, so the timer is written once.
             @keydown.stop as well as @click.stop: .stop on @click only guards
             the mouse path, and a bubbling keydown would let Enter both toggle
             the timer and open the editor over the top of it. -->
        <button
            type="button"
            class="timer-button"
            :class="[{ 'timer-button-running': runningLog }, layout === 'stack' ? 'self-start' : '']"
            :aria-label="copy(runningLog ? 'stopTimer' : 'startTimer')"
            @click.stop="onTimerClick"
            @keydown.stop
        >
            <component :is="runningLog ? Square : Play" :size="11" aria-hidden="true" />
            <span v-if="elapsedLabel">{{ elapsedLabel }}</span>
        </button>
    </div>
</template>
