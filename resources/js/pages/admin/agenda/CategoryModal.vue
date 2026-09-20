<script setup>
import { computed, ref } from 'vue';
import AdminModal from '../../../components/ui/AdminModal.vue';
import AppInput from '../../../components/ui/AppInput.vue';
import AppSelect from '../../../components/ui/AppSelect.vue';
import AppIconSelect from '../../../components/ui/AppIconSelect.vue';
import AppButton from '../../../components/ui/AppButton.vue';
import { copy } from '../../../shared/i18n';

const props = defineProps({
    category: {
        type: Object,
        default: null, // null = creating
    },
    categories: {
        type: Array,
        required: true,
    },
    saving: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'save']);

// Keyed on the id, not on the object: AgendaPage passes a `{ parent_id }` stub
// to pre-select the parent when adding a subcategory, which is still a create.
const isEditing = computed(() => Boolean(props.category?.id));

const form = ref({
    name: props.category?.name ?? '',
    color: props.category?.color ?? '#2f75a8',
    icon: props.category?.icon ?? null,
    parent_id: props.category?.parent_id ?? null,
});

// Only one level of nesting exists, so a valid parent is a top-level category
// that has no children of its own — offering anything else would produce a
// grandchild that no view renders while tasks still point at it. Nothing may
// be its own parent. The category's *current* parent always stays in the list,
// otherwise editing a subcategory would show an empty select.
const parentOptions = computed(() => {
    const options = [{ label: copy('categoryNoParent'), value: null }];
    const currentParentId = props.category?.parent_id ?? null;
    const idsWithChildren = new Set(
        props.categories.map((candidate) => candidate.parent_id).filter(Boolean),
    );

    for (const candidate of props.categories) {
        if (candidate.id === props.category?.id) {
            continue;
        }

        const isCurrentParent = candidate.id === currentParentId;

        if (!isCurrentParent && (candidate.parent_id || idsWithChildren.has(candidate.id))) {
            continue;
        }

        options.push({ label: candidate.name, value: candidate.id });
    }

    return options;
});

// A handful of on-brand presets, so picking a colour doesn't mean hunting
// through the OS colour wheel for something that fits the palette.
const presetColors = [
    '#2f75a8', '#5b8aa6', '#7c9a6b', '#c5a064',
    '#a5773e', '#c0503f', '#8c8478', '#071523',
];

const submit = () => {
    if (!form.value.name.trim()) {
        return;
    }

    emit('save', {
        name: form.value.name.trim(),
        color: form.value.color,
        icon: form.value.icon || null,
        parent_id: form.value.parent_id,
    });
};
</script>

<template>
    <AdminModal
        :eyebrow="copy('categories')"
        :title="isEditing ? copy('categoryEdit') : copy('categoryAdd')"
        @close="$emit('close')"
    >
        <form id="category-form" class="admin-grid" novalidate @submit.prevent="submit">
            <label class="admin-full">
                {{ copy('categoryName') }}
                <AppInput v-model="form.name" required />
            </label>

            <label class="admin-full">
                {{ copy('categoryParent') }}
                <AppSelect v-model="form.parent_id" :options="parentOptions" />
            </label>

            <label class="admin-full">
                {{ copy('categoryIcon') }}
                <AppIconSelect v-model="form.icon" />
            </label>

            <div class="admin-full">
                <span class="block text-xs text-mute">{{ copy('categoryColor') }}</span>
                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                    <button
                        v-for="preset in presetColors"
                        :key="preset"
                        type="button"
                        class="color-swatch"
                        :class="{ active: form.color.toLowerCase() === preset.toLowerCase() }"
                        :style="{ background: preset }"
                        :aria-label="preset"
                        @click="form.color = preset"
                    ></button>

                    <!-- Escape hatch for anything outside the presets. -->
                    <input v-model="form.color" type="color" class="color-swatch-input" :aria-label="copy('categoryColor')">
                    <span class="text-xs tabular-nums text-faint">{{ form.color }}</span>
                </div>
            </div>
        </form>

        <!-- The bar is outside the form, so the submit button names it by id. -->
        <template #footer>
            <div class="admin-modal-actions">
                <AppButton type="button" variant="outline" @click="$emit('close')">{{ copy('taskCancel') }}</AppButton>
                <AppButton type="submit" form="category-form" variant="solid" :disabled="saving">
                    {{ saving ? copy('saving') : copy('categorySave') }}
                </AppButton>
            </div>
        </template>
    </AdminModal>
</template>