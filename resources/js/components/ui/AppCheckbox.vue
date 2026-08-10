<script setup>
import Checkbox from 'primevue/checkbox';

const model = defineModel({ type: Boolean, default: false });
</script>

<template>
    <label class="field-checkbox-root">
        <Checkbox
            v-model="model"
            binary
            unstyled
            :pt="{
                root: 'contents',
                input: 'field-checkbox-input',
                box: 'field-checkbox-box',
                icon: 'field-checkbox-icon',
            }"
        />
        <span><slot /></span>
    </label>
</template>
