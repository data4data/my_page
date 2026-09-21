<script setup>
import { computed } from 'vue';
import { Plus } from '@lucide/vue';
import TaskCard from './TaskCard.vue';
import AppButton from '../../../../components/ui/AppButton.vue';
import { addDays, parseServerDatetime, toDateKey } from '../../../../shared/planning';
import { copy, lang } from '../../../../shared/i18n';

const props = defineProps({
    weekStart: {
        type: Date,
        required: true,
    },
    tasks: {
        type: Array,
        required: true,
    },
});

defineEmits(['edit-task', 'add-task', 'start-timer', 'stop-timer']);

const locale = computed(() => (lang.value === 'nl' ? 'nl-NL' : 'en-US'));

const days = computed(() => Array.from({ length: 7 }, (_, index) => {
    const date = addDays(props.weekStart, index);
    const key = toDateKey(date);
    const isToday = key === toDateKey(new Date());

    const dayTasks = props.tasks
        .filter((task) => toDateKey(parseServerDatetime(task.start_datetime)) === key)
        .sort((a, b) => parseServerDatetime(a.start_datetime) - parseServerDatetime(b.start_datetime));

    return {
        key,
        date,
        isToday,
        weekdayLabel: date.toLocaleDateString(locale.value, { weekday: 'short' }),
        dayLabel: date.toLocaleDateString(locale.value, { day: 'numeric', month: 'short' }),
        tasks: dayTasks,
    };
}));
</script>

<template>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-7">
        <div v-for="day in days" :key="day.key" class="min-w-0">
            <div class="week-day-header" :class="{ 'week-day-header-today': day.isToday }">
                <span class="week-day-weekday">{{ day.weekdayLabel }}</span>
                <span class="week-day-date">{{ day.dayLabel }}</span>
            </div>

            <div class="mt-2 flex justify-end">
                <AppButton variant="icon" :aria-label="copy('addTask')" @click="$emit('add-task', day.date)">
                    <Plus :size="14" />
                </AppButton>
            </div>

            <div class="mt-2 flex flex-col gap-2">
                <TaskCard
                    v-for="task in day.tasks"
                    :key="task.id"
                    :task="task"
                    @edit="$emit('edit-task', $event)"
                    @start-timer="$emit('start-timer', $event)"
                    @stop-timer="$emit('stop-timer', $event)"
                />
                <p v-if="day.tasks.length === 0" class="week-day-empty">{{ copy('noTasksThisDay') }}</p>
            </div>
        </div>
    </div>
</template>
