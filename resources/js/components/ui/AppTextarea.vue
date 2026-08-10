<script setup>
import Textarea from 'primevue/textarea';

const model = defineModel({ type: String, default: '' });
</script>

<template>
    <Textarea v-model="model" class="field-textarea" unstyled />
</template>
