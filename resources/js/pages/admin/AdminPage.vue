<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowRight, Calendar, Inbox, Pencil } from '@lucide/vue';
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
};
const view = computed(() => Object.keys(routeNameForView).find((key) => routeNameForView[key] === route.name) ?? 'edit');
const goToView = (key) => router.push({ name: routeNameForView[key] });

const inquiries = ref([]);
const inquiriesLoading = ref(true);
// The connect form is public, so this list grows without bound over time and
// arrives one page at a time.
const inquiriesHasMore = ref(false);
const inquiriesPage = ref(1);

// computed (not a plain array) so labels re-render when the admin switches
// their own working language via the header EN/NL toggle.
const navItems = computed(() => [
    { key: 'agenda', label: copy('agenda'), icon: Calendar },
    { key: 'insights', label: copy('insights'), icon: Inbox },
    { key: 'edit', label: copy('editPage'), icon: Pencil },
]);

const adminTabs = computed(() => [
    { value: 'profile', label: copy('tabProfile') },
    { value: 'metrics', label: copy('tabExperience') },
    { value: 'expertise', label: copy('tabExpertise') },
    { value: 'process', label: copy('tabProcess') },
    { value: 'projects', label: copy('tabProjects') },
    { value: 'language', label: copy('tabLanguage'), right: true },
    { value: 'versions', label: copy('tabVersions') },
]);

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

const loadMoreInquiries = () => fetchInquiries(inquiriesPage.value + 1).catch(reportFailure);

fetchPortfolio().catch(reportFailure);
fetchInquiries().catch(reportFailure);
fetchRevisions().catch(reportFailure);

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
            @load-more="loadMoreInquiries"
        />

        <SectionTabs v-else v-model="tab" :tabs="adminTabs">
            <ProfileTab v-if="tab === 'profile'" :profile="profile" />
            <MetricsTab v-else-if="tab === 'metrics'" :metrics="metrics" :add-item="addItem" :remove-item="removeItem" :move-item="moveItem" />
            <ExpertiseTab v-else-if="tab === 'expertise'" :expertise="expertise" :add-item="addItem" :remove-item="removeItem" :move-item="moveItem" />
            <ProcessTab v-else-if="tab === 'process'" :process-steps="processSteps" :add-item="addItem" :remove-item="removeItem" :move-item="moveItem" />
            <ProjectsTab v-else-if="tab === 'projects'" :projects="projects" :add-item="addItem" :remove-item="removeItem" :move-item="moveItem" :update-tags="updateTags" />
            <LanguageTab v-else-if="tab === 'language'" :profile="profile" />
            <ContentVersionsTab
                v-else-if="tab === 'versions'"
                :restoring="restoring"
                :restore-defaults="restoreDefaults"
                :revisions="revisions"
                :revisions-loading="revisionsLoading"
                :restore-revision="restoreRevision"
                :restoring-id="restoringId"
            />
        </SectionTabs>

        <template #fab>
            <!-- Editor-only: this saves the portfolio content payload, which
                 means nothing on Agenda or Insights (both persist through
                 their own endpoints) — and it was overlapping their own save
                 buttons. -->
            <AppButton v-if="view === 'edit'" variant="primary" size="sm" class="fab-save" :disabled="saving" @click="savePortfolio">
                {{ saving ? copy('saving') : copy('save') }}
                <ArrowRight :size="16" />
            </AppButton>
        </template>
    </AdminLayout>
</template>
