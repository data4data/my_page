<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowRight, Calendar, Inbox, Pencil, Settings } from '@lucide/vue';
import AppButton from '../../components/ui/AppButton.vue';
import AdminLayout from '../../components/admin/AdminLayout.vue';
import SectionTabs from '../../components/admin/SectionTabs.vue';
import ProfileTab from './ProfileTab.vue';
import MetricsTab from './MetricsTab.vue';
import ExpertiseTab from './ExpertiseTab.vue';
import ProcessTab from './ProcessTab.vue';
import ProjectsTab from './ProjectsTab.vue';
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
// Settings keeps its own tab state: switching sections should not carry an
// Edit-page tab across into it.
const settingsTab = ref('language');

// Saved versions of the public page, listed on the Content versions tab.
const revisions = ref([]);
const revisionsLoading = ref(true);
const restoringId = ref(null);

// Top-level admin section — driven by the URL (each has its own real,
// bookmarkable/refreshable path) rather than local component state, so
// switching sections pushes a route instead of just flipping a ref.
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
// The connect form is public, so this list grows without bound over time and
// arrives one page at a time.
const inquiriesHasMore = ref(false);
const inquiriesPage = ref(1);

const securityEvents = ref(null);
const securityLoading = ref(true);

// computed (not a plain array) so labels re-render when the admin switches
// their own working language via the header EN/NL toggle.
const navItems = computed(() => [
    { key: 'agenda', label: copy('agenda'), icon: Calendar },
    { key: 'insights', label: copy('insights'), icon: Inbox },
    { key: 'edit', label: copy('editPage'), icon: Pencil },
    { key: 'settings', label: copy('settings'), icon: Settings },
]);

// Edit page is now only the content itself. Language and Content versions
// moved to Settings: neither is page copy, and both are changed far less
// often than the text around them.
const adminTabs = computed(() => [
    { value: 'profile', label: copy('tabProfile') },
    { value: 'metrics', label: copy('tabExperience') },
    { value: 'expertise', label: copy('tabExpertise') },
    { value: 'process', label: copy('tabProcess') },
    { value: 'projects', label: copy('tabProjects') },
]);

const settingsTabs = computed(() => [
    { value: 'language', label: copy('tabLanguage') },
    { value: 'two-factor', label: copy('tabTwoFactor') },
    { value: 'versions', label: copy('tabVersions') },
]);

// The Language tab edits fields in the portfolio payload, so it needs the
// same Save button the Edit page has. The other two settings persist through
// their own endpoints the moment you act on them.
const showSaveButton = computed(() => view.value === 'edit' || (view.value === 'settings' && settingsTab.value === 'language'));

// Each of the three initial loads clears its own flag in `finally` and reports
// its own failure: one of them failing must not leave that panel spinning, nor
// take the other two down with it.
const fetchInquiries = async (page = 1) => {
    inquiriesLoading.value = true;

    try {
        const body = await apiFetch(`${adminUrl('/inquiries')}?page=${page}`);
        const rows = body.inquiries ?? [];

        // Page one replaces, later pages append — so "load more" grows the
        // list while a refresh still starts clean.
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

fetchPortfolio().catch(reportFailure);
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

        await fetchPortfolio();
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

// Asks first, like restoreRevision below. It sits in the same list now, one
// click away from the saved versions, and it is the more destructive of the
// two — the warning note that used to guard it is gone.
const restoreDefaults = async () => {
    if (!await confirm({ message: copy('restoreConfirm'), confirmLabel: copy('historyRestore') })) {
        return;
    }

    restoring.value = true;

    try {
        await apiFetch(adminUrl('/portfolio/seed-defaults'), { method: 'POST', message: copy('error') });

        await fetchPortfolio();
        await fetchRevisions();
        toast.success(copy('restored'));
    } catch (error) {
        reportFailure(error);
    } finally {
        restoring.value = false;
    }
};

// Restoring overwrites the live public page, so it asks first — via the shared
// confirm() singleton rather than window.confirm.
const restoreRevision = async (id) => {
    if (!await confirm({ message: copy('historyConfirm'), confirmLabel: copy('historyRestore') })) {
        return;
    }

    restoringId.value = id;

    try {
        await apiFetch(adminUrl(`/portfolio/revisions/${id}/restore`), { method: 'POST', message: copy('error') });

        await fetchPortfolio();
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
    <main v-if="loading" class="min-h-screen bg-white px-6 py-10 text-ink">
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

        <SectionTabs v-else-if="view === 'settings'" v-model="settingsTab" :tabs="settingsTabs">
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
        </SectionTabs>

        <SectionTabs v-else v-model="tab" :tabs="adminTabs">
            <ProfileTab v-if="tab === 'profile'" :profile="profile" />
            <MetricsTab v-else-if="tab === 'metrics'" :metrics="metrics" :add-item="addItem" :remove-item="removeItem" :move-item="moveItem" />
            <ExpertiseTab v-else-if="tab === 'expertise'" :expertise="expertise" :add-item="addItem" :remove-item="removeItem" :move-item="moveItem" />
            <ProcessTab v-else-if="tab === 'process'" :process-steps="processSteps" :add-item="addItem" :remove-item="removeItem" :move-item="moveItem" />
            <ProjectsTab v-else-if="tab === 'projects'" :projects="projects" :add-item="addItem" :remove-item="removeItem" :move-item="moveItem" :update-tags="updateTags" />
        </SectionTabs>

        <template #fab>
            <!-- Only where there is unsaved payload to write: the Edit page,
                 and Settings' Language tab, which edits the same payload.
                 Agenda, Insights and the other settings persist through their
                 own endpoints, and the button was overlapping their own. -->
            <AppButton v-if="showSaveButton" variant="primary" size="sm" class="fab-save" :disabled="saving" @click="savePortfolio">
                {{ saving ? copy('saving') : copy('save') }}
                <ArrowRight :size="16" />
            </AppButton>
        </template>
    </AdminLayout>
</template>
