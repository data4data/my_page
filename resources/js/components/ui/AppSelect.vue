<script setup>
import Select from 'primevue/select';

defineProps({
    options: {
        type: Array, // [{ label, value }]
        required: true,
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
        unstyled
        :pt="{
            root: 'field-select-root',
            label: 'field-select-label',
            dropdown: 'field-select-dropdown',
            overlay: 'field-select-overlay',
            list: 'field-select-list',
            option: 'field-select-option',
        }"
    />
</template>
