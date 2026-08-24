<script setup>
import { computed } from 'vue';
import TaskCard from './TaskCard.vue';
import { parseServerDatetime, toDateKey } from '../../../shared/planning';
import { copy } from '../../../shared/i18n';

const props = defineProps({
    day: {
        type: Date,
        required: true,
    },
    tasks: {
        type: Array,
        required: true,
    },
});

defineEmits(['edit-task', 'start-timer', 'stop-timer']);

const dayTasks = computed(() => {
    const key = toDateKey(props.day);

    return props.tasks
        .filter((task) => toDateKey(parseServerDatetime(task.start_datetime)) === key)
        .sort((a, b) => parseServerDatetime(a.start_datetime) - parseServerDatetime(b.start_datetime));
});
</script>

<template>
    <div class="flex flex-col gap-2">
        <TaskCard
            v-for="task in dayTasks"
            :key="task.id"
            :task="task"
            @edit="$emit('edit-task', $event)"
            @start-timer="$emit('start-timer', $event)"
            @stop-timer="$emit('stop-timer', $event)"
        />
        <p v-if="dayTasks.length === 0" class="week-day-empty">{{ copy('noTasksThisDay') }}</p>
    </div>
</template>
