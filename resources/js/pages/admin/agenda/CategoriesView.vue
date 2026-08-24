<script setup>
import { computed, ref } from 'vue';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import AppButton from '../../../components/ui/AppButton.vue';
import CategoryModal from './CategoryModal.vue';
import { iconMap } from '../../../shared/icons';
import { copy } from '../../../shared/i18n';
import { usePlanning } from '../../../shared/planning';
import { useToast } from '../../../shared/toast';
import { useConfirm } from '../../../shared/confirm';

const { categories, fetchCategories, createCategory, updateCategory, deleteCategory } = usePlanning();
const toast = useToast();
const { confirm } = useConfirm();

// This view holds its own usePlanning() instance, so the calendar's copy of
// the category list won't see edits made here — tell it to refetch.
const emit = defineEmits(['changed']);

const loading = ref(true);
const showModal = ref(false);
const editing = ref(null);
const saving = ref(false);

// Two fixed columns: plain categories on the left, ones with subcategories on
// the right — rather than a single flow, so the short rows stay together
// instead of being interrupted by tall nested groups. Within the nested
// column, fewest subcategories first; alphabetical as the tie-break in both.
const sortedCategories = computed(() => [...categories.value].sort((a, b) => {
    const byChildCount = (a.children?.length ?? 0) - (b.children?.length ?? 0);

    return byChildCount || a.name.localeCompare(b.name);
}));

const columns = computed(() => [
    sortedCategories.value.filter((category) => !category.children?.length),
    sortedCategories.value.filter((category) => category.children?.length),
]);

const load = async () => {
    loading.value = true;
    await fetchCategories();
    loading.value = false;
};

const openCreate = (parentId = null) => {
    editing.value = parentId === null ? null : { parent_id: parentId };
    showModal.value = true;
};

const openEdit = (category) => {
    editing.value = category;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editing.value = null;
};

const handleSave = async (payload) => {
    saving.value = true;

    try {
        // `editing` doubles as a pre-filled parent for "add subcategory",
        // which has no id yet — so only treat it as an edit when it has one.
        if (editing.value?.id) {
            await updateCategory(editing.value.id, payload);
        } else {
            await createCategory(payload);
        }

        closeModal();
        toast.success(copy('categorySaved'));
        await load();
        emit('changed');
    } catch {
        toast.error(copy('categoryError'));
    } finally {
        saving.value = false;
    }
};

const handleDelete = async (category) => {
    const hasChildren = (category.children ?? []).length > 0;

    const confirmed = await confirm({
        message: hasChildren ? copy('categoryDeleteParentConfirm') : copy('categoryDeleteConfirm'),
        confirmLabel: copy('confirmDelete'),
    });

    if (!confirmed) {
        return;
    }

    try {
        await deleteCategory(category.id);
        toast.success(copy('categoryDeleted'));
        await load();
        emit('changed');
    } catch {
        toast.error(copy('categoryError'));
    }
};

load();
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs leading-5 text-taupe">{{ copy('categoriesHint') }}</p>
            <AppButton variant="accent" size="sm" @click="openCreate()">
                <Plus :size="14" /> {{ copy('categoryAdd') }}
            </AppButton>
        </div>

        <p v-if="loading" class="admin-note mt-4">{{ copy('loading') }}</p>
        <p v-else-if="categories.length === 0" class="week-day-empty mt-4">{{ copy('categoriesEmpty') }}</p>

        <!-- Column one holds the plain categories, column two the ones with
             subcategories (see `columns`). Iterating the pair keeps the row
             markup in a single place rather than duplicating it per column.
             Below lg the grid collapses to one column and they simply stack. -->
        <div v-else class="mt-4 grid items-start gap-2 lg:grid-cols-2">
            <ul v-for="(column, index) in columns" :key="index" class="flex flex-col gap-2">
            <li v-for="category in column" :key="category.id" class="category-row-group">
                <div class="category-row">
                    <span class="category-swatch" :style="{ background: category.color }"></span>
                    <component :is="iconMap[category.icon]" v-if="iconMap[category.icon]" :size="15" class="shrink-0 text-graphite" />
                    <span class="flex-1 truncate text-sm text-ink">{{ category.name }}</span>

                    <AppButton variant="icon" :aria-label="copy('categoryAddChild')" @click="openCreate(category.id)">
                        <Plus :size="14" />
                    </AppButton>
                    <AppButton variant="icon" :aria-label="copy('categoryEdit')" @click="openEdit(category)">
                        <Pencil :size="14" />
                    </AppButton>
                    <AppButton variant="icon-danger" :aria-label="copy('categoryDelete')" @click="handleDelete(category)">
                        <Trash2 :size="14" />
                    </AppButton>
                </div>

                <div v-for="child in category.children ?? []" :key="child.id" class="category-row category-row-child">
                    <span class="category-swatch" :style="{ background: child.color }"></span>
                    <component :is="iconMap[child.icon]" v-if="iconMap[child.icon]" :size="15" class="shrink-0 text-graphite" />
                    <span class="flex-1 truncate text-sm text-ink">{{ child.name }}</span>

                    <AppButton variant="icon" :aria-label="copy('categoryEdit')" @click="openEdit(child)">
                        <Pencil :size="14" />
                    </AppButton>
                    <AppButton variant="icon-danger" :aria-label="copy('categoryDelete')" @click="handleDelete(child)">
                        <Trash2 :size="14" />
                    </AppButton>
                </div>
            </li>
            </ul>
        </div>

        <CategoryModal
            v-if="showModal"
            :category="editing"
            :categories="categories"
            :saving="saving"
            @close="closeModal"
            @save="handleSave"
        />
    </div>
</template>
