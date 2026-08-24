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

// Option rows: the <li> itself carries MultiSelect's select handler, so the
// checkbox here is purely decorative and its input can stay screen-reader
// only — same shape AppCheckbox uses, so it looks identical app-wide.
const optionCheckboxPt = {
    root: 'contents',
    input: 'field-checkbox-input',
    box: 'field-checkbox-box',
    icon: 'field-checkbox-icon',
};

// The "select all" checkbox has no such wrapper row, and PrimeVue's Checkbox
// binds onChange to its <input> alone (AppCheckbox only works because its
// root is a <label> around it). With an sr-only input there is nothing left
// to click, which left select-all silently dead — so stretch a transparent
// input across the box instead of hiding it.
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
