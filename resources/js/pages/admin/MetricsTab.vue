<script setup>
import { Plus } from '@lucide/vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppTranslatedField from '../../components/ui/AppTranslatedField.vue';
import AppCheckbox from '../../components/ui/AppCheckbox.vue';
import AppButton from '../../components/ui/AppButton.vue';
import EditableCard from '../../components/EditableCard.vue';
import { copy } from '../../shared/i18n';

defineProps({
    metrics: {
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
</script>

<template>
    <div class="space-y-4">
        <div class="admin-note">
            These numbers fill the stat row under the hero (e.g. "10+ Years of experience"). Change order, value, label text, and visibility here.
        </div>
        <p v-if="metrics.length === 0" class="admin-note">{{ copy('empty') }}</p>
        <EditableCard v-for="(item, index) in metrics" :key="index" title="Metric" :index="index" collection="metrics" @move="moveItem" @remove="removeItem">
            <label class="admin-full">Value<AppInput v-model="item.value" /></label>
            <div class="admin-full"><AppTranslatedField label="Label" v-model="item.label" /></div>
            <AppCheckbox v-model="item.is_visible">Visible</AppCheckbox>
        </EditableCard>
        <AppButton variant="primary" size="sm" class="fab-add" @click="addItem('metrics', { value: '1+', label: { en: 'New metric', nl: 'Nieuwe metriek' } })"><Plus :size="16" /> Add metric</AppButton>
    </div>
</template>
