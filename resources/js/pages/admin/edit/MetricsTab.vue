<script setup>
import { Plus } from '@lucide/vue';
import AppInput from '../../../components/ui/AppInput.vue';
import AppButton from '../../../components/ui/AppButton.vue';
import AppLanguageCards from '../../../components/ui/AppLanguageCards.vue';
import EditableCard from '../../../components/EditableCard.vue';
import { copy, t } from '../../../shared/i18n';

// No intro paragraph: the sheet's subtitle says what the tab is for, and the
// split itself says the rest — the value sits in the card head because it is
// the same in both languages and is what the card is about, the label sits
// inside the language cards because it is not.
defineProps({
    metrics: {
        type: Array,
        required: true,
    },
    profile: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['add', 'move', 'remove']);

const COLLECTION = 'metrics';

// What "add" starts from. In the script, not inside the click handler: a whole
// object written into markup is a default nobody finds when they go looking.
const blank = () => ({
    value: '1+',
    label: { en: 'New metric', nl: 'Nieuwe metriek' },
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
            :visible="item.is_visible !== false"
            @move="(from, direction) => emit('move', COLLECTION, from, direction)"
            @remove="(at) => emit('remove', COLLECTION, at)"
            @update:visible="item.is_visible = $event"
        >
            <template #head-field>
                <label class="item-card-head-field">
                    {{ copy('fieldValue') }}
                    <AppInput v-model="item.value" />
                </label>
            </template>

            <AppLanguageCards :default-language="profile.default_language">
                <template #default="{ locale }">
                    <label class="field-label">{{ copy('fieldLabel') }}<AppInput v-model="item.label[locale]" /></label>
                </template>
            </AppLanguageCards>
        </EditableCard>

        <AppButton variant="solid" class="self-start" @click="emit('add', COLLECTION, blank())">
            <Plus :size="14" aria-hidden="true" />
            {{ copy('addMetric') }}
        </AppButton>
    </div>
</template>
