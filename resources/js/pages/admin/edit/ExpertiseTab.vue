<script setup>
import { Plus } from '@lucide/vue';
import AppInput from '../../../components/ui/AppInput.vue';
import AppTextarea from '../../../components/ui/AppTextarea.vue';
import AppIconSelect from '../../../components/ui/AppIconSelect.vue';
import AppButton from '../../../components/ui/AppButton.vue';
import AppLanguageCards from '../../../components/ui/AppLanguageCards.vue';
import EditableCard from '../../../components/EditableCard.vue';
import { copy, t } from '../../../shared/i18n';

defineProps({
    expertise: {
        type: Array,
        required: true,
    },
    profile: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['add', 'move', 'remove']);

const COLLECTION = 'expertise_items';

// What "add" starts from. In the script, not inside the click handler: a whole
// object written into markup is a default nobody finds when they go looking.
const blank = () => ({
    title: { en: 'New expertise', nl: 'Nieuwe expertise' },
    description: { en: 'Describe the result and capability.', nl: 'Beschrijf het resultaat en de vaardigheid.' },
    icon: 'sparkles',
    category: 'General',
});
</script>

<template>
    <div class="flex flex-col gap-3">
        <p v-if="expertise.length === 0" class="admin-note">{{ copy('empty') }}</p>

        <EditableCard
            v-for="(item, index) in expertise"
            :key="index"
            :title="t(item.title) || copy('untitled')"
            :index="index"
            :total="expertise.length"
            :visible="item.is_visible !== false"
            @move="(from, direction) => emit('move', COLLECTION, from, direction)"
            @remove="(at) => emit('remove', COLLECTION, at)"
            @update:visible="item.is_visible = $event"
        >
            <div class="lang-grid">
                <label class="field-label">{{ copy('fieldIcon') }}<AppIconSelect v-model="item.icon" /></label>
                <label class="field-label">{{ copy('fieldCategory') }}<AppInput v-model="item.category" /></label>
            </div>

            <AppLanguageCards :default-language="profile.default_language">
                <template #default="{ locale }">
                    <label class="field-label">{{ copy('fieldTitle') }}<AppInput v-model="item.title[locale]" /></label>
                    <label class="field-label">{{ copy('fieldDescription') }}<AppTextarea v-model="item.description[locale]" rows="2" /></label>
                </template>
            </AppLanguageCards>
        </EditableCard>

        <AppButton
            variant="solid"
            class="self-start"
            @click="emit('add', COLLECTION, blank())"
        >
            <Plus :size="14" aria-hidden="true" />
            {{ copy('addExpertise') }}
        </AppButton>
    </div>
</template>
