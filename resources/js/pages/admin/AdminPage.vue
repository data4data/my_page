<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Calendar, Inbox, Pencil, Settings } from '@lucide/vue';
import AppButton from '../../components/ui/AppButton.vue';
import AdminLayout from '../../components/admin/AdminLayout.vue';
import AdminSheet from '../../components/admin/AdminSheet.vue';
import ProfileTab from './ProfileTab.vue';
import MetricsTab from './MetricsTab.vue';
import ExpertiseTab from './ExpertiseTab.vue';
import ProcessTab from './ProcessTab.vue';
import ProjectsTab from './ProjectsTab.vue';
import SocialLinksTab from './SocialLinksTab.vue';
import SharedTab from './SharedTab.vue';
import InsightsPage from './InsightsPage.vue';
import ContentVersionsTab from './ContentVersionsTab.vue';
import LanguageTab from './LanguageTab.vue';
import TwoFactorCard from './TwoFactorCard.vue';
import AgendaPage from './AgendaPage.vue';
import ToastStack from '../../components/ui/ToastStack.vue';
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue';
import { copy } from '../../shared/i18n';
import { adminUrl } from '../../shared/admin-path';
import { usePortfolioSource } from '../../shared/portfolio';
import { apiFetch } from '../../shared/api';
import { useToast } from '../../shared/toast';
import { useConfirm } from '../../shared/confirm';

const toast = useToast();
const { confirm } = useConfirm();
const route = useRoute();
const router = useRouter();

const {
    data,
    loading,
    fetchPortfolio,
    profile,
    metrics,
    expertise,
    projects,
    processSteps,
} = usePortfolioSource(adminUrl('/portfolio'));

const saving = ref(false);
const restoring = ref(false);
const tab = ref('profile');
// Its own tab state, so switching sections does not carry one across.
const settingsTab = ref('language');

// Saved versions of the public page, listed on the Content versions tab.
const revisions = ref([]);
const revisionsLoading = ref(true);
const restoringId = ref(null);

// Driven by the URL, so every section is bookmarkable and refreshable.
const routeNameForView = {
    agenda: 'admin-agenda',
    insights: 'admin-insights',
    edit: 'admin-edit',
    settings: 'admin-settings',
};
const view = computed(() => Object.keys(routeNameForView).find((key) => routeNameForView[key] === route.name) ?? 'edit');
const goToView = (key) => router.push({ name: routeNameForView[key] });

const inquiries = ref([]);
const inquiriesLoading = ref(true);
// The connect form is public, so this list arrives a page at a time.
const inquiriesHasMore = ref(false);
const inquiriesPage = ref(1);

const securityEvents = ref(null);
const securityLoading = ref(true);

// computed so the labels re-render when the EN/NL toggle changes.
const navItems = computed(() => [
    { key: 'agenda', label: copy('agenda'), icon: Calendar },
    { key: 'insights', label: copy('insights'), icon: Inbox },
    { key: 'edit', label: copy('editPage'), icon: Pencil },
    // Pinned to the bottom of the rail, apart from the three destinations.
    { key: 'settings', label: copy('settings'), icon: Settings, foot: true },
]);

// Edit page is the content only. Language and Content versions are in
// Settings: neither is page copy.
const adminTabs = computed(() => [
    { value: 'profile', label: copy('tabProfile') },
    { value: 'metrics', label: copy('tabExperience') },
    { value: 'expertise', label: copy('tabExpertise') },
    { value: 'process', label: copy('tabProcess') },
    { value: 'projects', label: copy('tabProjects') },
    { value: 'social', label: copy('tabSocial') },
    // Trailing, like Insights' Security tab: values that are the same in
    // both languages, rather than another slice of page copy.
    { value: 'shared', label: copy('tabShared'), right: true },
]);

const settingsTabs = computed(() => [
    { value: 'language', label: copy('tabLanguage') },
    { value: 'two-factor', label: copy('tabTwoFactor') },
    { value: 'versions', label: copy('tabVersions') },
]);

// Language edits the portfolio payload, so it needs the Save button. The
// other settings save through their own endpoints as you act on them.
const showSaveButton = computed(() => view.value === 'edit' || (view.value === 'settings' && settingsTab.value === 'language'));

// What the server last confirmed, serialised. The action bar says "Unsaved
// changes" against this rather than against a flag set by a deep watcher: a
// watcher also fires when fetchPortfolio() replaces the payload, so a plain
// reload would have reported edits nobody made.
const savedPayload = ref('');
const dirty = computed(() => Boolean(savedPayload.value) && savedPayload.value !== JSON.stringify(data.value));
const saveStatus = computed(() => (dirty.value ? copy('unsavedChanges') : copy('allSaved')));

// One subtitle per tab, under the page title.
const EDIT_SUBTITLES = {
    profile: 'subtitleEditProfile',
    metrics: 'subtitleEditMetrics',
    expertise: 'subtitleEditExpertise',
    process: 'subtitleEditProcess',
    projects: 'subtitleEditProjects',
    social: 'subtitleEditSocial',
    shared: 'subtitleEditShared',
};

const SETTINGS_SUBTITLES = {
    language: 'subtitleSettingsLanguage',
    'two-factor': 'subtitleSettingsTwoFactor',
    versions: 'subtitleSettingsVersions',
};

const editSubtitle = computed(() => copy(EDIT_SUBTITLES[tab.value] ?? ''));
const settingsSubtitle = computed(() => copy(SETTINGS_SUBTITLES[settingsTab.value] ?? ''));

// Each load clears its own flag and reports its own failure, so one failing
// leaves the others alone.
const fetchInquiries = async (page = 1) => {
    inquiriesLoading.value = true;

    try {
        const body = await apiFetch(`${adminUrl('/inquiries')}?page=${page}`);
        const rows = body.inquiries ?? [];

        // Page one replaces, later pages append.
        inquiries.value = page === 1 ? rows : [...inquiries.value, ...rows];
        inquiriesHasMore.value = body.has_more ?? false;
        inquiriesPage.value = body.page ?? page;
    } finally {
        inquiriesLoading.value = false;
    }
};

const fetchRevisions = async () => {
    revisionsLoading.value = true;

    try {
        revisions.value = (await apiFetch(adminUrl('/portfolio/revisions'))).revisions ?? [];
    } finally {
        revisionsLoading.value = false;
    }
};

const reportFailure = (error) => toast.error(error.message || copy('error'));

const fetchSecurityEvents = async () => {
    securityLoading.value = true;

    try {
        securityEvents.value = await apiFetch(adminUrl('/security-events'));
    } finally {
        securityLoading.value = false;
    }
};

const loadMoreInquiries = () => fetchInquiries(inquiriesPage.value + 1).catch(reportFailure);

// Snapshot once the payload lands, so `dirty` has something to compare to.
const loadPortfolio = () => fetchPortfolio().then(() => {
    savedPayload.value = JSON.stringify(data.value);
});

loadPortfolio().catch(reportFailure);
fetchInquiries().catch(reportFailure);
fetchRevisions().catch(reportFailure);
fetchSecurityEvents().catch(reportFailure);

const savePortfolio = async () => {
    saving.value = true;

    try {
        await apiFetch(adminUrl('/portfolio'), {
            method: 'PUT',
            body: data.value,
            message: copy('error'),
        });

        await loadPortfolio();
        // The save just created a new version — refresh the list so it shows up
        // without a page reload.
        await fetchRevisions();
        toast.success(copy('saved'));
    } catch (error) {
        reportFailure(error);
    } finally {
        saving.value = false;
    }
};

// Asks first, like restoreRevision: it is the more destructive of the two and
// sits one click away from the saved versions.
const restoreDefaults = async () => {
    if (!await confirm({ message: copy('restoreConfirm'), confirmLabel: copy('historyRestore') })) {
        return;
    }

    restoring.value = true;

    try {
        await apiFetch(adminUrl('/portfolio/seed-defaults'), { method: 'POST', message: copy('error') });

        await loadPortfolio();
        await fetchRevisions();
        toast.success(copy('restored'));
    } catch (error) {
        reportFailure(error);
    } finally {
        restoring.value = false;
    }
};

// Restoring overwrites the live public page, so it asks first.
const restoreRevision = async (id) => {
    if (!await confirm({ message: copy('historyConfirm'), confirmLabel: copy('historyRestore') })) {
        return;
    }

    restoringId.value = id;

    try {
        await apiFetch(adminUrl(`/portfolio/revisions/${id}/restore`), { method: 'POST', message: copy('error') });

        await loadPortfolio();
        await fetchRevisions();
        toast.success(copy('historyRestored'));
    } catch (error) {
        reportFailure(error);
    } finally {
        restoringId.value = null;
    }
};

const addItem = (collection, item) => {
    data.value[collection].push({ ...item, is_visible: true });
};

const removeItem = (collection, index) => {
    data.value[collection].splice(index, 1);
};

const moveItem = (collection, index, direction) => {
    const next = index + direction;
    if (next < 0 || next >= data.value[collection].length) {
        return;
    }

    const items = data.value[collection];
    [items[index], items[next]] = [items[next], items[index]];
};

const updateTags = (project, value) => {
    project.tags = value.split(',').map((tag) => tag.trim()).filter(Boolean);
};
</script>

<template>
    <main v-if="loading" class="min-h-dvh px-6 py-10" style="background: var(--color-ground); color: var(--color-body)">
        <div class="mx-auto max-w-7xl">{{ copy('loading') }}</div>
    </main>

    <AdminLayout v-else :nav-items="navItems" :active-key="view" :initials="profile.initials" :profile="profile" @navigate="goToView">
        <ToastStack />
        <ConfirmDialog />

        <AgendaPage v-if="view === 'agenda'" />

        <InsightsPage
            v-else-if="view === 'insights'"
            :inquiries="inquiries"
            :inquiries-loading="inquiriesLoading"
            :inquiries-has-more="inquiriesHasMore"
            :security-events="securityEvents"
            :security-loading="securityLoading"
            @load-more="loadMoreInquiries"
        />

        <AdminSheet
            v-else-if="view === 'settings'"
            v-model="settingsTab"
            :tabs="settingsTabs"
            :title="copy('settings')"
            :subtitle="settingsSubtitle"
        >
            <LanguageTab v-if="settingsTab === 'language'" :profile="profile" />
            <TwoFactorCard v-else-if="settingsTab === 'two-factor'" />
            <ContentVersionsTab
                v-else-if="settingsTab === 'versions'"
                :restoring="restoring"
                :restore-defaults="restoreDefaults"
                :revisions="revisions"
                :revisions-loading="revisionsLoading"
                :restore-revision="restoreRevision"
                :restoring-id="restoringId"
            />

            <!-- Only Language puts anything in the unsaved payload; the other
                 two settings tabs persist as you act on them. -->
            <template v-if="showSaveButton" #status>{{ saveStatus }}</template>

            <template v-if="showSaveButton" #actions>
                <AppButton variant="outline" :disabled="!dirty || saving" @click="loadPortfolio().catch(reportFailure)">
                    {{ copy('cancel') }}
                </AppButton>
                <AppButton variant="solid" :disabled="saving" @click="savePortfolio">
                    {{ saving ? copy('saving') : copy('save') }}
                </AppButton>
            </template>
        </AdminSheet>

        <AdminSheet
            v-else
            v-model="tab"
            :tabs="adminTabs"
            :title="copy('editPage')"
            :subtitle="editSubtitle"
        >
            <ProfileTab v-if="tab === 'profile'" :profile="profile" />
            <MetricsTab v-else-if="tab === 'metrics'" :metrics="metrics" :add-item="addItem" :remove-item="removeItem" :move-item="moveItem" :profile="profile" />
            <ExpertiseTab v-else-if="tab === 'expertise'" :expertise="expertise" :add-item="addItem" :remove-item="removeItem" :move-item="moveItem" :profile="profile" />
            <ProcessTab v-else-if="tab === 'process'" :process-steps="processSteps" :add-item="addItem" :remove-item="removeItem" :move-item="moveItem" :profile="profile" />
            <ProjectsTab v-else-if="tab === 'projects'" :projects="projects" :add-item="addItem" :remove-item="removeItem" :move-item="moveItem" :update-tags="updateTags" :profile="profile" />
            <SocialLinksTab v-else-if="tab === 'social'" :profile="profile" />
            <SharedTab v-else-if="tab === 'shared'" :profile="profile" />

            <template #status>{{ saveStatus }}</template>

            <template #actions>
                <AppButton variant="outline" :disabled="!dirty || saving" @click="loadPortfolio().catch(reportFailure)">
                    {{ copy('cancel') }}
                </AppButton>
                <AppButton variant="solid" :disabled="saving" @click="savePortfolio">
                    {{ saving ? copy('saving') : copy('save') }}
                </AppButton>
            </template>
        </AdminSheet>
    </AdminLayout>
</template>
