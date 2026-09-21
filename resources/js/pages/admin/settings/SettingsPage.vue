<script setup>
import { computed, ref } from 'vue';
import AdminSheet from '../../../components/admin/AdminSheet.vue';
import AppButton from '../../../components/ui/AppButton.vue';
import LanguageTab from './LanguageTab.vue';
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
    { value: 'two-factor', label: copy('tabTwoFactor') },
    { value: 'versions', label: copy('tabVersions') },
]);

const SUBTITLES = {
    language: 'subtitleSettingsLanguage',
    'two-factor': 'subtitleSettingsTwoFactor',
    versions: 'subtitleSettingsVersions',
};

const subtitle = computed(() => copy(SUBTITLES[tab.value] ?? ''));

// Language is the only tab here that puts anything in the unsaved payload; the
// other two persist through their own endpoints the moment you act on them.
const showSaveButton = computed(() => tab.value === 'language');
</script>

<template>
    <AdminSheet v-model="tab" :tabs="tabs" :title="copy('settings')" :subtitle="subtitle">
        <LanguageTab v-if="tab === 'language'" :profile="profile" />
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
