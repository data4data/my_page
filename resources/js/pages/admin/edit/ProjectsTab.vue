<script setup>
import { computed, onMounted, ref } from 'vue';
import AppInput from '../../../components/ui/AppInput.vue';
import AppSelect from '../../../components/ui/AppSelect.vue';
import AppMultiSelect from '../../../components/ui/AppMultiSelect.vue';
import AppTextarea from '../../../components/ui/AppTextarea.vue';
import AppLanguageCards from '../../../components/ui/AppLanguageCards.vue';
import EditableCard from '../../../components/EditableCard.vue';
import { copy, t } from '../../../shared/i18n';
import { VISUAL_STYLES } from '../../../shared/portfolio';
import { useTags } from '../../../shared/tags';
import { reportError } from '../../../shared/api';
import { useToast } from '../../../shared/toast';

defineProps({
    projects: {
        type: Array,
        required: true,
    },
    profile: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['move', 'remove']);

const COLLECTION = 'projects';

// One key per style rather than a built string, so a missing translation is
// visible in the dictionary rather than only at runtime.
const VISUAL_STYLE_LABELS = {
    dashboard: 'visualStyleDashboard',
    flow: 'visualStyleFlow',
    cms: 'visualStyleCms',
};

// computed, so the labels re-render on the EN/NL toggle.
const visualStyleOptions = computed(() => VISUAL_STYLES.map((value) => ({
    value,
    label: copy(VISUAL_STYLE_LABELS[value]),
})));

const { tags, loadTags, createTag } = useTags();
const toast = useToast();

// One shared list for every card on the tab, so a tag added on one is offered
// by the next without a reload.
const tagOptions = computed(() => tags.value.map((name) => ({ value: name, label: name })));

onMounted(() => loadTags().catch((failure) => reportError(failure, copy('tagsLoadError'))));

// What was typed into the dropdown's search box. Kept so the "add" row can
// offer that exact word when nothing matches it.
const tagSearch = ref('');
const addingTag = ref(false);

const addTag = async (item) => {
    const name = tagSearch.value.trim();

    if (!name || addingTag.value) {
        return;
    }

    addingTag.value = true;

    try {
        const created = await createTag(name);
        // Selected on the card it was added from: adding a word you are not
        // then given is a second step for no reason.
        item.tags = [...(item.tags ?? []), created];
        tagSearch.value = '';
        toast.success(copy('tagAdded'));
    } catch (failure) {
        reportError(failure, copy('tagAddError'));
    } finally {
        addingTag.value = false;
    }
};

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
                <label class="field-label">{{ copy('fieldVisualStyle') }}<AppSelect v-model="item.visual_style" :options="visualStyleOptions" /></label>
                <label class="field-label">
                    {{ copy('fieldTags') }}
                    <AppMultiSelect
                        :model-value="item.tags ?? []"
                        :options="tagOptions"
                        filterable
                        :placeholder="copy('fieldTagsPlaceholder')"
                        :filter-placeholder="copy('tagSearch')"
                        @update:model-value="item.tags = $event"
                        @filter="tagSearch = $event.value"
                    >
                        <!-- Searched for and not found: the one moment the
                             offer to create it is worth making. -->
                        <template #emptyfilter>
                            <button type="button" class="field-select-add" :disabled="addingTag" @click="addTag(item)">
                                {{ copy('tagAdd') }} “{{ tagSearch }}”
                            </button>
                        </template>
                    </AppMultiSelect>
                </label>
            </div>

            <AppLanguageCards :default-language="profile.default_language">
                <template #default="{ locale }">
                    <label class="field-label">{{ copy('fieldTitle') }}<AppInput v-model="item.title[locale]" /></label>
                    <label class="field-label">{{ copy('fieldSummary') }}<AppTextarea v-model="item.summary[locale]" rows="2" /></label>
                    <label class="field-label">{{ copy('fieldResult') }}<AppInput v-model="item.result[locale]" /></label>
                </template>
            </AppLanguageCards>
        </EditableCard>
    </div>
</template>
