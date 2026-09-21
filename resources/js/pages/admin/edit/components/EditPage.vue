<script setup>
import { computed, ref } from 'vue';
import AdminSheet from '../../../../components/admin/AdminSheet.vue';
import { Plus } from '@lucide/vue';
import AppButton from '../../../../components/ui/AppButton.vue';
import ProfileTab from './ProfileTab.vue';
import MetricsTab from './MetricsTab.vue';
import ExpertiseTab from './ExpertiseTab.vue';
import ProcessTab from './ProcessTab.vue';
import ProjectsTab from './ProjectsTab.vue';
import SocialLinksTab from './SocialLinksTab.vue';
import GeneralTab from './GeneralTab.vue';
import { copy } from '../../../../shared/i18n';
import { usePortfolioEditor } from '../../usePortfolioEditor';
import { addActionFor } from '../composables/new-item';

const {
    profile,
    metrics,
    expertise,
    projects,
    processSteps,
    socialLinks,
    loading,
    loadFailed,
    ready,
    dirty,
    saving,
    saveStatus,
    reload,
    save,
    addItem,
    removeItem,
    moveItem,
} = usePortfolioEditor();

const tab = ref('profile');

// computed, so the labels re-render on the EN/NL toggle.
const tabs = computed(() => [
    { value: 'profile', label: copy('tabProfile') },
    { value: 'metrics', label: copy('tabMetrics') },
    { value: 'expertise', label: copy('tabExpertise') },
    { value: 'process', label: copy('tabProcess') },
    { value: 'projects', label: copy('tabProjects') },
    { value: 'social', label: copy('tabSocial') },
    // Trailing: the values that are the same in both languages.
    { value: 'general', label: copy('tabGeneral'), right: true },
]);

const SUBTITLES = {
    profile: 'subtitleEditProfile',
    metrics: 'subtitleEditMetrics',
    expertise: 'subtitleEditExpertise',
    process: 'subtitleEditProcess',
    projects: 'subtitleEditProjects',
    social: 'subtitleEditSocial',
    general: 'subtitleEditGeneral',
};

const subtitle = computed(() => copy(SUBTITLES[tab.value] ?? ''));

// In the action bar rather than under the list: the bar is sticky, so adding
// a tenth project does not mean scrolling past nine to find the button. Null
// on the two tabs that edit the profile itself and have no list to add to.
const addAction = computed(() => addActionFor(tab.value));
</script>

<template>
    <AdminSheet v-model="tab" :tabs="tabs" :title="copy('editPage')" :subtitle="subtitle">
        <p v-if="loading" class="admin-note">{{ copy('loading') }}</p>

        <!-- A page with no content and a page that failed to arrive both draw
             an empty editor, and saving that one would publish the blank. -->
        <div v-else-if="loadFailed" class="flex flex-col items-start gap-3">
            <p class="admin-note">{{ copy('contentLoadError') }}</p>
            <AppButton variant="solid" @click="reload">{{ copy('retry') }}</AppButton>
        </div>

        <template v-else>
            <ProfileTab v-if="tab === 'profile'" :profile="profile" />
            <MetricsTab
                v-else-if="tab === 'metrics'"
                :metrics="metrics"
                :profile="profile"
                @remove="removeItem"
                @move="moveItem"
            />
            <ExpertiseTab
                v-else-if="tab === 'expertise'"
                :expertise="expertise"
                :profile="profile"
                @remove="removeItem"
                @move="moveItem"
            />
            <ProcessTab
                v-else-if="tab === 'process'"
                :process-steps="processSteps"
                :profile="profile"
                @remove="removeItem"
                @move="moveItem"
            />
            <ProjectsTab
                v-else-if="tab === 'projects'"
                :projects="projects"
                :profile="profile"
                @remove="removeItem"
                @move="moveItem"
            />
            <SocialLinksTab
                v-else-if="tab === 'social'"
                :links="socialLinks"
                @remove="removeItem"
                @move="moveItem"
            />
            <GeneralTab v-else-if="tab === 'general'" :profile="profile" />
        </template>

        <template #status>{{ saveStatus }}</template>

        <template #actions>
            <!-- First, and quieter than Save: it is the tab's own action, not
                 the sheet's. Disabled until the payload is there to add to. -->
            <AppButton
                v-if="addAction"
                variant="outline"
                :disabled="!ready"
                @click="addItem(addAction.collection, addAction.blank())"
            >
                <Plus :size="14" aria-hidden="true" />
                {{ addAction.label }}
            </AppButton>

            <AppButton variant="outline" :disabled="!dirty || saving" @click="reload">
                {{ copy('cancel') }}
            </AppButton>
            <!-- Disabled until a payload has actually arrived: saving nothing
                 would replace the live page with nothing. -->
            <AppButton variant="solid" :disabled="saving || !ready" @click="save">
                {{ saving ? copy('saving') : copy('save') }}
            </AppButton>
        </template>
    </AdminSheet>
</template>
