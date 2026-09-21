<script setup>
import { Plus } from '@lucide/vue';
import AppInput from '../../../components/ui/AppInput.vue';
import AppTextarea from '../../../components/ui/AppTextarea.vue';
import AppButton from '../../../components/ui/AppButton.vue';
import AppLanguageCards from '../../../components/ui/AppLanguageCards.vue';
import EditableCard from '../../../components/EditableCard.vue';
import { copy, t } from '../../../shared/i18n';

defineProps({
    projects: {
        type: Array,
        required: true,
    },
    profile: {
        type: Object,
        required: true,
    },
    updateTags: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(['add', 'move', 'remove']);

const COLLECTION = 'projects';

// What "add" starts from. In the script, not inside the click handler: a whole
// object written into markup is a default nobody finds when they go looking.
const blank = () => ({
    title: { en: 'New project', nl: 'Nieuw project' },
    summary: { en: 'Describe the system and result.', nl: 'Beschrijf het systeem en resultaat.' },
    result: { en: 'What improved.', nl: 'Wat is verbeterd.' },
    tags: ['Laravel'],
    visual_style: 'dashboard',
});
</script>

<template>
    <div class="flex flex-col gap-3">
        <p v-if="projects.length === 0" class="admin-note">{{ copy('empty') }}</p>

        <EditableCard
            v-for="(item, index) in projects"
            :key="index"
            :title="t(item.title) || copy('untitled')"
            :index="index"
            :total="projects.length"
            :visible="item.is_visible !== false"
            @move="(from, direction) => emit('move', COLLECTION, from, direction)"
            @remove="(at) => emit('remove', COLLECTION, at)"
            @update:visible="item.is_visible = $event"
        >
            <div class="lang-grid">
                <label class="field-label">{{ copy('fieldVisualStyle') }}<AppInput v-model="item.visual_style" placeholder="dashboard, flow, cms" /></label>
                <label class="field-label">{{ copy('fieldTags') }}<AppInput :model-value="item.tags?.join(', ')" @update:model-value="(value) => updateTags(item, value)" /></label>
            </div>

            <AppLanguageCards :default-language="profile.default_language">
                <template #default="{ locale }">
                    <label class="field-label">{{ copy('fieldTitle') }}<AppInput v-model="item.title[locale]" /></label>
                    <label class="field-label">{{ copy('fieldSummary') }}<AppTextarea v-model="item.summary[locale]" rows="2" /></label>
                    <label class="field-label">{{ copy('fieldResult') }}<AppInput v-model="item.result[locale]" /></label>
                </template>
            </AppLanguageCards>
        </EditableCard>

        <AppButton
            variant="solid"
            class="self-start"
            @click="emit('add', COLLECTION, blank())"
        >
            <Plus :size="14" aria-hidden="true" />
            {{ copy('addProject') }}
        </AppButton>
    </div>
</template>
