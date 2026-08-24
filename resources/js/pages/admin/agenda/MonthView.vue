<script setup>
import { computed } from 'vue';
import { Plus } from '@lucide/vue';
import AppButton from '../../../components/ui/AppButton.vue';
import { addDays, parseServerDatetime, startOfWeek, toDateKey } from '../../../shared/planning';
import { copy, lang } from '../../../shared/i18n';

const props = defineProps({
    monthStart: {
        type: Date,
        required: true, // always the 1st of the displayed month
    },
    tasks: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['select-day', 'edit-task', 'add-task']);

const locale = computed(() => (lang.value === 'nl' ? 'nl-NL' : 'en-US'));

// Fixed 6x7 grid (42 days) so every month's layout is stable regardless of
// how many weeks it actually spans or which weekday it starts on.
const gridStart = computed(() => startOfWeek(props.monthStart));

const weekdayLabels = computed(() => {
    const start = gridStart.value;
    return Array.from({ length: 7 }, (_, index) => addDays(start, index).toLocaleDateString(locale.value, { weekday: 'short' }));
});

const days = computed(() => {
    const monthIndex = props.monthStart.getMonth();
    const todayKey = toDateKey(new Date());

    return Array.from({ length: 42 }, (_, index) => {
        const date = addDays(gridStart.value, index);
        const key = toDateKey(date);

        const dayTasks = props.tasks
            .filter((task) => toDateKey(parseServerDatetime(task.start_datetime)) === key)
            .sort((a, b) => parseServerDatetime(a.start_datetime) - parseServerDatetime(b.start_datetime));

        return {
            key,
            date,
            dayNumber: date.getDate(),
            inMonth: date.getMonth() === monthIndex,
            isToday: key === todayKey,
            tasks: dayTasks,
        };
    });
});

const maxVisibleTasks = 3;
</script>

<template>
    <div>
        <div class="grid grid-cols-7 gap-2">
            <p v-for="label in weekdayLabels" :key="label" class="week-day-weekday text-center">{{ label }}</p>
        </div>

        <div class="mt-2 grid grid-cols-7 gap-2">
            <div
                v-for="day in days"
                :key="day.key"
                role="button"
                tabindex="0"
                class="month-cell"
                :class="{ 'month-cell-outside': !day.inMonth, 'month-cell-today': day.isToday }"
                @click="emit('select-day', day.date)"
                @keydown.enter="emit('select-day', day.date)"
            >
                <span class="month-cell-day">{{ day.dayNumber }}</span>
                <button
                    v-for="task in day.tasks.slice(0, maxVisibleTasks)"
                    :key="task.id"
                    type="button"
                    class="month-task-chip"
                    :style="{ '--task-color': task.category?.color ?? 'var(--color-steel)' }"
                    @click.stop="emit('edit-task', task)"
                    @keydown.stop
                >
                    {{ task.title }}
                </button>
                <span v-if="day.tasks.length > maxVisibleTasks" class="month-task-more">+{{ day.tasks.length - maxVisibleTasks }}</span>

                <!-- @keydown.stop alongside @click.stop on both the chips and
                     this button: without it, Enter fires the inner action and
                     also navigates the view underneath to Day view. -->
                <AppButton variant="icon" class="absolute bottom-1 right-1 h-7 w-7" :aria-label="copy('addTask')" @click.stop="emit('add-task', day.date)" @keydown.stop>
                    <Plus :size="12" />
                </AppButton>
            </div>
        </div>
    </div>
</template>
