<script setup>
import { computed, ref } from 'vue';
import { ExternalLink, Newspaper } from '@lucide/vue';
import AdminSheet from '../../../components/admin/AdminSheet.vue';
import AppButton from '../../../components/ui/AppButton.vue';
import SecurityTab from './SecurityTab.vue';
import { copy } from '../../../shared/i18n';
import { adminUrl } from '../../../shared/admin-path';
import { apiFetch, reportError } from '../../../shared/api';

/*
 * This section loads what it shows. Nothing else in the workspace reads the
 * messages or the sign-in trail, so asking for them from the shell meant
 * opening Agenda still fetched both.
 */
const inquiries = ref([]);
const inquiriesLoading = ref(true);
// The connect form is public, so this list arrives a page at a time.
const inquiriesHasMore = ref(false);
const inquiriesPage = ref(1);

const securityEvents = ref(null);
const securityLoading = ref(true);

const tab = ref('connections');

// Each load clears its own flag, so one failing leaves the others alone.
const fetchInquiries = async (page = 1) => {
    inquiriesLoading.value = true;

    try {
        const body = await apiFetch(`${adminUrl('/inquiries')}?page=${page}`);
        const rows = body.inquiries ?? [];

        inquiries.value = page === 1 ? rows : [...inquiries.value, ...rows];
        inquiriesHasMore.value = body.has_more ?? false;
        inquiriesPage.value = body.page ?? page;
    } finally {
        inquiriesLoading.value = false;
    }
};

const fetchSecurityEvents = async () => {
    securityLoading.value = true;

    try {
        securityEvents.value = await apiFetch(adminUrl('/security-events'));
    } finally {
        securityLoading.value = false;
    }
};

const loadInquiries = (page = 1) => fetchInquiries(page)
    .catch((error) => reportError(error, copy('inquiryLoadError')));

loadInquiries();
fetchSecurityEvents().catch((error) => reportError(error, copy('securityLoadError')));

// Security sits last and to the right: it is a log you check, not a feed you
// read.
const insightsTabs = computed(() => [
    { value: 'connections', label: copy('insightsConnections') },
    { value: 'news', label: copy('insightsNews') },
    { value: 'security', label: copy('insightsSecurity'), right: true },
]);

const SUBTITLES = {
    connections: 'subtitleInsightsConnections',
    news: 'subtitleInsightsNews',
    security: 'subtitleInsightsSecurity',
};

const subtitle = computed(() => copy(SUBTITLES[tab.value] ?? ''));

// Only the connections tab has a count worth stating; the others say nothing
// rather than filling the bar with something untrue.
const status = computed(() => {
    if (tab.value !== 'connections' || inquiriesLoading.value) {
        return '';
    }

    // The label as translated: which words keep a capital is a language rule.
    return `${inquiries.value.length}${inquiriesHasMore.value ? '+' : ''} · ${copy('insightsConnections')}`;
});

const formatDate = (value) => new Date(value).toLocaleString(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});
</script>

<template>
    <AdminSheet v-model="tab" :tabs="insightsTabs" :title="copy('insights')" :subtitle="subtitle">
        <div v-if="tab === 'connections'" class="flex flex-col gap-2.5">
            <p class="admin-note">{{ copy('insightsInfo') }}</p>

            <p v-if="inquiriesLoading" class="admin-note">{{ copy('insightsLoading') }}</p>
            <p v-else-if="inquiries.length === 0" class="admin-note">{{ copy('insightsEmpty') }}</p>

            <article v-for="inquiry in inquiries" :key="inquiry.id" class="inquiry-card">
                <header class="inquiry-card-head">
                    <strong class="inquiry-card-name">{{ inquiry.name }}</strong>
                    <time class="inquiry-card-time" :datetime="inquiry.created_at">{{ formatDate(inquiry.created_at) }}</time>
                </header>

                <div class="inquiry-card-body">
                    <p class="inquiry-field">
                        <span class="inquiry-field-label">{{ copy('insightsEmail') }}</span>
                        <a :href="`mailto:${inquiry.email}`">{{ inquiry.email }}</a>
                    </p>
                    <p v-if="inquiry.company" class="inquiry-field">
                        <span class="inquiry-field-label">{{ copy('insightsCompany') }}</span>
                        <span class="truncate">{{ inquiry.company }}</span>
                    </p>
                    <p v-if="inquiry.portfolio_url" class="inquiry-field">
                        <span class="inquiry-field-label">{{ copy('insightsPortfolio') }}</span>
                        <a :href="inquiry.portfolio_url" target="_blank" rel="noopener">
                            <span class="truncate">{{ inquiry.portfolio_url }}</span>
                            <ExternalLink :size="12" aria-hidden="true" />
                        </a>
                    </p>
                    <p v-if="inquiry.linkedin_url" class="inquiry-field">
                        <span class="inquiry-field-label">{{ copy('insightsLinkedin') }}</span>
                        <a :href="inquiry.linkedin_url" target="_blank" rel="noopener">
                            <span class="truncate">{{ inquiry.linkedin_url }}</span>
                            <ExternalLink :size="12" aria-hidden="true" />
                        </a>
                    </p>
                    <p class="inquiry-message whitespace-pre-line">{{ inquiry.message }}</p>
                </div>
            </article>

            <AppButton
                v-if="inquiriesHasMore"
                variant="outline"
                class="self-start"
                :disabled="inquiriesLoading"
                @click="loadInquiries(inquiriesPage + 1)"
            >
                {{ inquiriesLoading ? copy('insightsLoading') : copy('insightsLoadMore') }}
            </AppButton>
        </div>

        <!-- Says plainly that it holds nothing, rather than showing an empty
             list that looks like a failed load. -->
        <div v-else-if="tab === 'news'" class="flex flex-col gap-2.5">
            <div class="admin-empty">
                <span class="admin-empty-icon"><Newspaper :size="20" aria-hidden="true" /></span>
                <strong class="admin-empty-title">{{ copy('underConstruction') }}</strong>
                <p class="admin-empty-note">{{ copy('insightsNewsInfo') }}</p>
            </div>
        </div>

        <SecurityTab v-else :events="securityEvents" :loading="securityLoading" />

        <template v-if="status" #status>{{ status }}</template>
    </AdminSheet>
</template>
