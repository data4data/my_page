<script setup>
import { computed, ref } from 'vue';
import AdminSheet from '../../../components/admin/AdminSheet.vue';
import AppButton from '../../../components/ui/AppButton.vue';
import LanguageTab from './LanguageTab.vue';
import CategoriesTab from './CategoriesTab.vue';
import TwoFactorCard from './TwoFactorCard.vue';
import ContentVersionsTab from './ContentVersionsTab.vue';
import { copy } from '../../../shared/i18n';
import { usePortfolioEditor } from '../usePortfolioEditor';

const {
    profile,
    ready,
    dirty,
    saving,
    saveStatus,
    restoring,
    restoringId,
    revisions,
    revisionsLoading,
    reload,
    save,
    restoreDefaults,
    restoreRevision,
} = usePortfolioEditor();

const tab = ref('language');

const tabs = computed(() => [
    { value: 'language', label: copy('tabLanguage') },
    // Task categories live here rather than in Agenda: they are set up once
    // and then used, which is what everything else on this sheet has in common.
    { value: 'categories', label: copy('categories') },
    { value: 'two-factor', label: copy('tabTwoFactor') },
    { value: 'versions', label: copy('tabVersions') },
]);

const SUBTITLES = {
    language: 'subtitleSettingsLanguage',
    categories: 'subtitleSettingsCategories',
    'two-factor': 'subtitleSettingsTwoFactor',
    versions: 'subtitleSettingsVersions',
};

const subtitle = computed(() => copy(SUBTITLES[tab.value] ?? ''));

// Language is the only tab here that puts anything in the unsaved payload —
// and only its two language rows at that. Everything else on this sheet
// persists through its own endpoint the moment you act on it.
const showSaveButton = computed(() => tab.value === 'language');
</script>

<template>
    <AdminSheet v-model="tab" :tabs="tabs" :title="copy('settings')" :subtitle="subtitle">
        <LanguageTab v-if="tab === 'language'" :profile="profile" />
        <CategoriesTab v-else-if="tab === 'categories'" />
        <TwoFactorCard v-else-if="tab === 'two-factor'" />
        <ContentVersionsTab
            v-else-if="tab === 'versions'"
            :restoring="restoring"
            :restore-defaults="restoreDefaults"
            :revisions="revisions"
            :revisions-loading="revisionsLoading"
            :restore-revision="restoreRevision"
            :restoring-id="restoringId"
        />

        <template v-if="showSaveButton" #status>{{ saveStatus }}</template>

        <template v-if="showSaveButton" #actions>
            <AppButton variant="outline" :disabled="!dirty || saving" @click="reload">
                {{ copy('cancel') }}
            </AppButton>
            <AppButton variant="solid" :disabled="saving || !ready" @click="save">
                {{ saving ? copy('saving') : copy('save') }}
            </AppButton>
        </template>
    </AdminSheet>
</template>
