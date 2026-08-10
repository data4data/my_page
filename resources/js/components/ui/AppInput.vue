<script setup>
import InputText from 'primevue/inputtext';

defineProps({
    type: {
        type: String,
        default: 'text',
    },
});

// Support v-model.number (used for the numeric gear-size field) the same
// way native <input v-model.number> would, since custom components don't
// get that for free.
const [model, modifiers] = defineModel({
    default: '',
    set(value) {
        return modifiers.number ? (Number(value) || 0) : value;
    },
});
</script>

<template>
    <InputText v-model="model" :type="type" class="field-input" unstyled />
</template>
