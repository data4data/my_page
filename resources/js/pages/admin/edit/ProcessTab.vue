<script setup>
import { computed } from 'vue';
import AppTextarea from '../../../components/ui/AppTextarea.vue';
import AppInput from '../../../components/ui/AppInput.vue';
import AppIconSelect from '../../../components/ui/AppIconSelect.vue';
import AppSelect from '../../../components/ui/AppSelect.vue';
import AppLanguageCards from '../../../components/ui/AppLanguageCards.vue';
import EditableCard from '../../../components/EditableCard.vue';
import { copy, t } from '../../../shared/i18n';

defineProps({
    processSteps: {
        type: Array,
        required: true,
    },
    profile: {
        type: Object,
        required: true,
    },
});

// The three groups are a fixed vocabulary on the public panel, not free text,
// so they stay a select. Labels are computed for the EN/NL switch.
const processGroupOptions = computed(() => [
    { label: copy('processGroupInput'), value: 'input' },
    { label: copy('processGroupCore'), value: 'core' },
    { label: copy('processGroupOutput'), value: 'output' },
]);

const emit = defineEmits(['move', 'remove']);

const COLLECTION = 'process_steps';
</script>

<template>
    <div class="flex flex-col gap-3">
        <p v-if="processSteps.length === 0" class="admin-note">{{ copy('empty') }}</p>

        <EditableCard
            v-for="(item, index) in processSteps"
            :key="index"
            :title="t(item.title) || copy('untitled')"
            :index="index"
            :total="processSteps.length"
            :visible="item.is_visible !== false"
            @move="(from, direction) => emit('move', COLLECTION, from, direction)"
            @remove="(at) => emit('remove', COLLECTION, at)"
            @update:visible="item.is_visible = $event"
        >
            <div class="lang-grid">
                <label class="field-label">{{ copy('fieldIcon') }}<AppIconSelect v-model="item.icon" /></label>
                <label class="field-label">{{ copy('fieldGroup') }}<AppSelect v-model="item.group" :options="processGroupOptions" /></label>
            </div>

            <AppLanguageCards :default-language="profile.default_language">
                <template #default="{ locale }">
                    <label class="field-label">{{ copy('fieldTitle') }}<AppInput v-model="item.title[locale]" /></label>
                    <label class="field-label">{{ copy('fieldDescription') }}<AppTextarea v-model="item.description[locale]" rows="2" /></label>
                </template>
            </AppLanguageCards>
        </EditableCard>
    </div>
</template>
