<script setup>
import { Plus } from '@lucide/vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppIconSelect from '../../components/ui/AppIconSelect.vue';
import AppTranslatedField from '../../components/ui/AppTranslatedField.vue';
import AppCheckbox from '../../components/ui/AppCheckbox.vue';
import AppButton from '../../components/ui/AppButton.vue';
import EditableCard from '../../components/EditableCard.vue';
import { copy } from '../../shared/i18n';

defineProps({
    expertise: {
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
            These items fill the public Expertise carousel. Change order, text, icon, and visibility here.
        </div>
        <p v-if="expertise.length === 0" class="admin-note">{{ copy('empty') }}</p>
        <EditableCard v-for="(item, index) in expertise" :key="index" title="Expertise" :index="index" collection="expertise_items" @move="moveItem" @remove="removeItem">
            <div class="admin-full"><AppTranslatedField label="Title" v-model="item.title" /></div>
            <label>Icon<AppIconSelect v-model="item.icon" /></label>
            <label>Category<AppInput v-model="item.category" /></label>
            <div class="admin-full"><AppTranslatedField label="Description" v-model="item.description" multiline rows="2" /></div>
            <AppCheckbox v-model="item.is_visible">Visible</AppCheckbox>
        </EditableCard>
        <AppButton variant="primary" size="sm" class="fab-add" @click="addItem('expertise_items', { title: { en: 'New expertise', nl: 'Nieuwe expertise' }, description: { en: 'Describe the result and capability.', nl: 'Beschrijf het resultaat en de expertise.' }, icon: 'sparkles', category: 'general' })"><Plus :size="16" /> Add expertise</AppButton>
    </div>
</template>
