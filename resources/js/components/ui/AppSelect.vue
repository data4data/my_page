<script setup>
import Select from 'primevue/select';

const props = defineProps({
    options: {
        type: Array, // [{ label, value }]
        required: true,
    },
    filterable: {
        type: Boolean,
        default: false,
    },
    filterPlaceholder: {
        type: String,
        default: 'Search...',
    },
});

const model = defineModel({ type: [String, Number, null], default: null });
</script>

<template>
    <Select
        v-model="model"
        :options="options"
        option-label="label"
        option-value="value"
        :filter="filterable"
        :filter-placeholder="filterPlaceholder"
        unstyled
        :pt="{
            root: 'field-select-root',
            label: 'field-select-label',
            dropdown: 'field-select-dropdown',
            overlay: 'field-select-overlay',
            header: 'field-select-filter-header',
            pcFilterContainer: 'field-select-filter-field',
            pcFilter: { root: 'field-input' },
            pcFilterIconContainer: 'field-select-filter-icon',
            list: 'field-select-list',
            option: 'field-select-option',
        }"
    >
        <template v-if="$slots.value" #value="slotProps"><slot name="value" v-bind="slotProps" /></template>
        <template v-if="$slots.option" #option="slotProps"><slot name="option" v-bind="slotProps" /></template>
    </Select>
</template>
