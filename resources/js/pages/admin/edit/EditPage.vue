<script setup>
import { computed, ref } from 'vue';
import AdminSheet from '../../../components/admin/AdminSheet.vue';
import AppButton from '../../../components/ui/AppButton.vue';
import ProfileTab from './ProfileTab.vue';
import MetricsTab from './MetricsTab.vue';
import ExpertiseTab from './ExpertiseTab.vue';
import ProcessTab from './ProcessTab.vue';
import ProjectsTab from './ProjectsTab.vue';
import SocialLinksTab from './SocialLinksTab.vue';
import SharedTab from './SharedTab.vue';
import { copy } from '../../../shared/i18n';
import { usePortfolioEditor } from '../usePortfolioEditor';

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
    { value: 'metrics', label: copy('tabExperience') },
    { value: 'expertise', label: copy('tabExpertise') },
    { value: 'process', label: copy('tabProcess') },
    { value: 'projects', label: copy('tabProjects') },
    { value: 'social', label: copy('tabSocial') },
    // Trailing: the values that are the same in both languages.
    { value: 'shared', label: copy('tabShared'), right: true },
]);

const SUBTITLES = {
    profile: 'subtitleEditProfile',
    metrics: 'subtitleEditMetrics',
    expertise: 'subtitleEditExpertise',
    process: 'subtitleEditProcess',
    projects: 'subtitleEditProjects',
    social: 'subtitleEditSocial',
    shared: 'subtitleEditShared',
};

const subtitle = computed(() => copy(SUBTITLES[tab.value] ?? ''));

const updateTags = (project, value) => {
    project.tags = value.split(',').map((tag) => tag.trim()).filter(Boolean);
};
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
                @add="addItem"
                @remove="removeItem"
                @move="moveItem"
            />
            <ExpertiseTab
                v-else-if="tab === 'expertise'"
                :expertise="expertise"
                :profile="profile"
                @add="addItem"
                @remove="removeItem"
                @move="moveItem"
            />
            <ProcessTab
                v-else-if="tab === 'process'"
                :process-steps="processSteps"
                :profile="profile"
                @add="addItem"
                @remove="removeItem"
                @move="moveItem"
            />
            <ProjectsTab
                v-else-if="tab === 'projects'"
                :projects="projects"
                :profile="profile"
                :update-tags="updateTags"
                @add="addItem"
                @remove="removeItem"
                @move="moveItem"
            />
            <SocialLinksTab
                v-else-if="tab === 'social'"
                :links="socialLinks"
                @add="addItem"
                @remove="removeItem"
                @move="moveItem"
            />
            <SharedTab v-else-if="tab === 'shared'" :profile="profile" />
        </template>

        <template #status>{{ saveStatus }}</template>

        <template #actions>
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
