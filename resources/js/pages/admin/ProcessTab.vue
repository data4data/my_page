<script setup>
import { Plus } from '@lucide/vue';
import AppIconSelect from '../../components/ui/AppIconSelect.vue';
import AppTranslatedField from '../../components/ui/AppTranslatedField.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppCheckbox from '../../components/ui/AppCheckbox.vue';
import AppButton from '../../components/ui/AppButton.vue';
import EditableCard from '../../components/EditableCard.vue';

defineProps({
    processSteps: {
        type: Array,
        required: true,
    },
    addItem: {
        type: Function,
        required: true,
    },
    removeItem: {
        type: Function,
        required: true,
    },
    moveItem: {
        type: Function,
        required: true,
    },
});

const processGroupOptions = [
    { label: 'Input', value: 'input' },
    { label: 'Core', value: 'core' },
    { label: 'Output', value: 'output' },
];
</script>

<template>
    <div class="space-y-4">
        <div class="admin-note">
            These steps fill the "How I work" input / core / output panel on the public page. Change order, group, text, icon, and visibility here.
        </div>
        <EditableCard v-for="(item, index) in processSteps" :key="index" title="Process step" :index="index" collection="process_steps" @move="moveItem" @remove="removeItem">
            <label>Group<AppSelect v-model="item.group" :options="processGroupOptions" /></label>
            <label>Icon<AppIconSelect v-model="item.icon" /></label>
            <div class="admin-full"><AppTranslatedField label="Title" v-model="item.title" /></div>
            <div class="admin-full"><AppTranslatedField label="Description" v-model="item.description" multiline rows="2" /></div>
            <AppCheckbox v-model="item.is_visible">Visible</AppCheckbox>
        </EditableCard>
        <AppButton variant="primary" size="sm" class="fab-add" @click="addItem('process_steps', { group: 'core', title: { en: 'New step', nl: 'Nieuwe stap' }, description: { en: 'Short description', nl: 'Korte beschrijving' }, icon: 'sparkles' })"><Plus :size="16" /> Add process step</AppButton>
    </div>
</template>
