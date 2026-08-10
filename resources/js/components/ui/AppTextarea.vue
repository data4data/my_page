<script setup>
import Textarea from 'primevue/textarea';

defineProps({
    invalid: {
        type: Boolean,
        default: false,
    },
});

const model = defineModel({ type: String, default: '' });
</script>

<template>
    <Textarea v-model="model" :class="['field-textarea', { 'field-textarea-invalid': invalid }]" unstyled />
</template>
