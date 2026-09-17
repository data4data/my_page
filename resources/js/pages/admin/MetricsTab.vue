<script setup>
import { Plus } from '@lucide/vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppLanguageCards from '../../components/ui/AppLanguageCards.vue';
import EditableCard from '../../components/EditableCard.vue';
import { copy, t } from '../../shared/i18n';

// No intro paragraph: the sheet's subtitle says what the tab is for, and the
// split itself says the rest — the value sits outside the language cards
// because it is the same in both, the label sits inside because it is not.
defineProps({
    metrics: {
        type: Array,
        required: true,
    },
    profile: {
        type: Object,
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
</script>

<template>
    <div class="flex flex-col gap-3">
        <p v-if="metrics.length === 0" class="admin-note">{{ copy('empty') }}</p>

        <EditableCard
            v-for="(item, index) in metrics"
            :key="index"
            :title="t(item.label) || copy('untitled')"
            :index="index"
            :total="metrics.length"
            collection="metrics"
            :visible="item.is_visible !== false"
            @move="moveItem"
            @remove="removeItem"
            @update:visible="item.is_visible = $event"
        >
            <label class="field-label max-w-[12rem]">{{ copy('fieldValue') }}<AppInput v-model="item.value" /></label>

            <AppLanguageCards :default-language="profile.default_language">
                <template #default="{ locale }">
                    <label class="field-label">{{ copy('fieldLabel') }}<AppInput v-model="item.label[locale]" /></label>
                </template>
            </AppLanguageCards>
        </EditableCard>

        <AppButton variant="solid" class="self-start" @click="addItem('metrics', { value: '1+', label: { en: 'New metric', nl: 'Nieuwe metriek' } })">
            <Plus :size="14" aria-hidden="true" />
            {{ copy('addMetric') }}
        </AppButton>
    </div>
</template>
