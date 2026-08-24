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
});

const emit = defineEmits(['edit', 'start-timer', 'stop-timer']);

const categoryColor = computed(() => props.task.category?.color ?? 'var(--color-steel)');
const categoryIcon = computed(() => (props.task.category?.icon ? iconMap[props.task.category.icon] : null));
const statusLabel = computed(() => copy(taskStatusLabelKey[props.task.status] ?? 'taskStatusPlanned'));

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
        :class="`task-card-${task.status}`"
        :style="{ '--task-color': categoryColor }"
        @click="emit('edit', task)"
        @keydown.enter="emit('edit', task)"
    >
        <!-- The timer button carries @keydown.stop: .stop on @click only
             guards the mouse path, and a bubbling keydown would let Enter
             both toggle the timer and open the editor over the top of it. -->
        <div class="flex items-center justify-between gap-2">
            <span class="task-card-category">{{ task.category?.name ?? copy('uncategorized') }}</span>
            <span class="task-card-status">{{ statusLabel }}</span>
        </div>
        <div class="mt-2 flex items-start justify-between gap-2">
            <p class="task-card-title">{{ task.title }}</p>
            <component :is="categoryIcon" v-if="categoryIcon" :size="14" class="mt-0.5 shrink-0 text-graphite" />
        </div>
        <div class="mt-2 flex items-center justify-between gap-2">
            <p class="task-card-time">{{ timeRange }}</p>
            <button
                type="button"
                class="timer-button"
                :class="{ 'timer-button-running': runningLog }"
                :aria-label="copy(runningLog ? 'stopTimer' : 'startTimer')"
                @click.stop="onTimerClick"
                @keydown.stop
            >
                <component :is="runningLog ? Square : Play" :size="11" />
                <span v-if="elapsedLabel">{{ elapsedLabel }}</span>
            </button>
        </div>
    </div>
</template>
