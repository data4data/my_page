<script setup>
import AppSelect from './AppSelect.vue';
import { iconMap, resolveIcon } from '../../shared/icons';

// Picks from the actual iconMap keys instead of a free-text field, so it's
// impossible to save an icon value that silently renders nothing on the
// public page (see the warning comment in shared/icons.js).
const options = Object.keys(iconMap)
    .sort()
    .map((key) => ({ label: key, value: key }));

const model = defineModel({ type: String, default: '' });
</script>

<template>
    <AppSelect v-model="model" :options="options" filterable filter-placeholder="Search icons...">
        <template #value="{ value }">
            <span v-if="value" class="field-select-icon-value">
                <component :is="resolveIcon(value)" :size="15" />
                {{ value }}
            </span>
        </template>
        <template #option="{ option }">
            <span class="field-select-icon-option">
                <component :is="resolveIcon(option.value)" :size="15" />
                {{ option.label }}
            </span>
        </template>
    </AppSelect>
</template>
