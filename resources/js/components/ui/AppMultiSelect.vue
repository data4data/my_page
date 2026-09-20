<script setup>
import MultiSelect from 'primevue/multiselect';

defineProps({
    options: {
        type: Array, // [{ label, value }]
        required: true,
    },
    placeholder: {
        type: String,
        default: '',
    },
});

const model = defineModel({ type: Array, default: () => [] });

// The <li> carries MultiSelect's own handler, so this checkbox is decorative
// and its input stays screen-reader only. An overlay here would double-toggle.
const optionCheckboxPt = {
    root: 'contents',
    input: 'field-checkbox-input',
    box: 'field-checkbox-box',
    icon: 'field-checkbox-icon',
};

// "Select all" has no such row, and PrimeVue binds onChange to the <input>
// alone — so the input is a transparent overlay rather than sr-only, which
// would leave nothing to click.
const headerCheckboxPt = {
    root: 'relative inline-grid place-items-center',
    input: 'field-checkbox-input-overlay',
    box: 'field-checkbox-box',
    icon: 'field-checkbox-icon',
};
</script>

<template>
    <MultiSelect
        v-model="model"
        :options="options"
        option-label="label"
        option-value="value"
        :placeholder="placeholder"
        :max-selected-labels="99"
        unstyled
        :pt="{
            root: 'field-select-root',
            labelContainer: 'field-select-label',
            label: 'field-select-label',
            dropdown: 'field-select-dropdown',
            overlay: 'field-select-overlay',
            listContainer: 'field-select-list',
            option: 'field-select-option flex items-center gap-2',
            header: 'field-select-filter-header',
            pcHeaderCheckbox: headerCheckboxPt,
            pcOptionCheckbox: optionCheckboxPt,
        }"
    />
</template>
