<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Calendar, Inbox, Pencil, Settings } from '@lucide/vue';
import AdminLayout from '../../components/admin/AdminLayout.vue';
import ToastStack from '../../components/ui/ToastStack.vue';
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue';
import CalendarView from './agenda/components/CalendarView.vue';
import InsightsPage from './insights/InsightsPage.vue';
import EditPage from './edit/components/EditPage.vue';
import SettingsPage from './settings/SettingsPage.vue';
import { copy } from '../../shared/i18n';
import { providePortfolioEditor } from './usePortfolioEditor';

const route = useRoute();
const router = useRouter();

/*
 * The shell: the rail, and which section is on screen. Each section loads what
 * it shows, so opening Agenda no longer fetches the messages, the sign-in
 * trail and the saved versions as well.
 *
 * The one exception is the public page's unsaved payload. Two sections edit it
 * — Edit page, and Settings' Language tab — so it is provided from here and an
 * edit made in one survives walking over to the other.
 */
const { profile, reload, loadRevisions } = providePortfolioEditor();

reload();
loadRevisions();

// Driven by the URL, so every section is bookmarkable and refreshable.
const routeNameForView = {
    agenda: 'admin-agenda',
    insights: 'admin-insights',
    edit: 'admin-edit',
    settings: 'admin-settings',
};

const view = computed(() => Object.keys(routeNameForView).find((key) => routeNameForView[key] === route.name) ?? 'edit');
const goToView = (key) => router.push({ name: routeNameForView[key] });

// computed, so the labels re-render on the EN/NL toggle.
const navItems = computed(() => [
    { key: 'agenda', label: copy('agenda'), icon: Calendar },
    { key: 'insights', label: copy('insights'), icon: Inbox },
    { key: 'edit', label: copy('editPage'), icon: Pencil },
    // Pinned to the bottom of the rail, apart from the destinations.
    { key: 'settings', label: copy('settings'), icon: Settings, foot: true },
]);
</script>

<template>
    <AdminLayout
        :nav-items="navItems"
        :active-key="view"
        :initials="profile.initials ?? ''"
        :profile="profile"
        @navigate="goToView"
    >
        <ToastStack />
        <ConfirmDialog />

        <CalendarView v-if="view === 'agenda'" />
        <InsightsPage v-else-if="view === 'insights'" />
        <SettingsPage v-else-if="view === 'settings'" />
        <EditPage v-else />
    </AdminLayout>
</template>
