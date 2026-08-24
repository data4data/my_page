<script setup>
import DatePicker from 'primevue/datepicker';

defineProps({
    showTime: {
        type: Boolean,
        default: false,
    },
});

const model = defineModel({ type: [Date, null], default: null });

// 24-hour, day-first in both languages — the native input used to follow the
// OS locale, which meant the field could disagree with the app's own EN/NL
// setting. This keeps one predictable format everywhere.
const dateFormat = 'dd/mm/yy';
</script>

<template>
    <DatePicker
        v-model="model"
        :show-time="showTime"
        hour-format="24"
        :date-format="dateFormat"
        show-icon
        icon-display="input"
        unstyled
        :pt="{
            root: 'field-datepicker-root',
            pcInputText: { root: 'field-input' },
            inputIconContainer: 'field-datepicker-icon',
            panel: 'field-datepicker-panel',
            header: 'field-datepicker-header',
            title: 'field-datepicker-title',
            selectMonth: 'field-datepicker-select',
            selectYear: 'field-datepicker-select',
            pcPrevButton: { root: 'field-datepicker-nav' },
            pcNextButton: { root: 'field-datepicker-nav' },
            dayView: 'field-datepicker-dayview',
            weekDayCell: 'field-datepicker-weekday-cell',
            weekDay: 'field-datepicker-weekday',
            dayCell: 'field-datepicker-day-cell',
            day: 'field-datepicker-day',
            monthView: 'field-datepicker-grid',
            month: 'field-datepicker-grid-item',
            yearView: 'field-datepicker-grid',
            year: 'field-datepicker-grid-item',
            timePicker: 'field-datepicker-timepicker',
            hourPicker: 'field-datepicker-time-unit',
            minutePicker: 'field-datepicker-time-unit',
            separatorContainer: 'field-datepicker-time-separator',
            pcIncrementButton: { root: 'field-datepicker-time-button' },
            pcDecrementButton: { root: 'field-datepicker-time-button' },
            buttonbar: 'field-datepicker-buttonbar',
            pcTodayButton: { root: 'light-button btn-compact' },
            pcClearButton: { root: 'light-button btn-compact' },
        }"
    />
</template>
