<script setup>
import { Plus } from '@lucide/vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppTranslatedField from '../../components/ui/AppTranslatedField.vue';
import AppCheckbox from '../../components/ui/AppCheckbox.vue';
import AppButton from '../../components/ui/AppButton.vue';
import EditableCard from '../../components/EditableCard.vue';

defineProps({
    projects: {
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
    updateTags: {
        type: Function,
        required: true,
    },
});
</script>

<template>
    <div class="space-y-4">
        <div class="admin-note">
            These items fill the Featured projects grid on the public page. Change order, text, tags, visual style, and visibility here.
        </div>
        <EditableCard v-for="(item, index) in projects" :key="index" title="Project" :index="index" collection="projects" @move="moveItem" @remove="removeItem">
            <div class="admin-full"><AppTranslatedField label="Title" v-model="item.title" /></div>
            <label class="admin-full">Visual style<AppInput v-model="item.visual_style" placeholder="dashboard, flow, cms" /></label>
            <div class="admin-full"><AppTranslatedField label="Summary" v-model="item.summary" multiline rows="2" /></div>
            <div class="admin-full"><AppTranslatedField label="Result" v-model="item.result" /></div>
            <label class="admin-full">Tags<AppInput :model-value="item.tags?.join(', ')" @update:model-value="(value) => updateTags(item, value)" /></label>
            <AppCheckbox v-model="item.is_visible">Visible</AppCheckbox>
        </EditableCard>
        <AppButton variant="accent" size="sm" class="fab-add" @click="addItem('projects', { title: { en: 'New project', nl: 'Nieuw project' }, summary: { en: 'Describe the system and result.', nl: 'Beschrijf het systeem en resultaat.' }, result: { en: 'What improved.', nl: 'Wat is verbeterd.' }, tags: ['Laravel'], visual_style: 'dashboard' })"><Plus :size="16" /> Add project</AppButton>
    </div>
</template>
