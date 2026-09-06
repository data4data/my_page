<script setup>
import { computed, ref } from 'vue';
import { ExternalLink } from '@lucide/vue';
import SectionTabs from '../../components/admin/SectionTabs.vue';
import AppButton from '../../components/ui/AppButton.vue';
import SecurityTab from './SecurityTab.vue';
import { copy } from '../../shared/i18n';

defineProps({
    inquiries: {
        type: Array,
        required: true,
    },
    inquiriesLoading: {
        type: Boolean,
        required: true,
    },
    inquiriesHasMore: {
        type: Boolean,
        default: false,
    },
    securityEvents: {
        type: Object,
        default: null,
    },
    securityLoading: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['load-more']);

const tab = ref('connections');

// computed so the labels re-render when the admin switches EN/NL.
// Security sits last and to the right: it is a log you check, not one of the
// two feeds you read. `right: true` pushes it across the strip, the same way
// SectionTabs handles any trailing tab.
const insightsTabs = computed(() => [
    { value: 'connections', label: copy('insightsConnections') },
    { value: 'news', label: copy('insightsNews') },
    { value: 'security', label: copy('insightsSecurity'), right: true },
]);

const formatDate = (value) => new Date(value).toLocaleString(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});
</script>

<template>
    <SectionTabs v-model="tab" :tabs="insightsTabs">
        <div v-if="tab === 'connections'" class="space-y-4">
            <div class="admin-note">
                {{ copy('insightsInfo') }}
            </div>
            <p v-if="inquiriesLoading" class="admin-note">{{ copy('insightsLoading') }}</p>
            <p v-else-if="inquiries.length === 0" class="admin-note">{{ copy('insightsEmpty') }}</p>
            <article v-for="inquiry in inquiries" :key="inquiry.id" class="editable-card">
                <header>
                    <strong>{{ inquiry.name }}</strong>
                    <span class="text-xs font-normal normal-case text-taupe">{{ formatDate(inquiry.created_at) }}</span>
                </header>
                <div class="admin-grid text-sm normal-case">
                    <p><span class="admin-note-label">{{ copy('insightsEmail') }}</span> <a class="text-link" :href="`mailto:${inquiry.email}`">{{ inquiry.email }}</a></p>
                    <p v-if="inquiry.company"><span class="admin-note-label">{{ copy('insightsCompany') }}</span> {{ inquiry.company }}</p>
                    <p v-if="inquiry.portfolio_url"><span class="admin-note-label">{{ copy('insightsPortfolio') }}</span> <a class="text-link" :href="inquiry.portfolio_url" target="_blank" rel="noopener">{{ inquiry.portfolio_url }} <ExternalLink :size="13" /></a></p>
                    <p v-if="inquiry.linkedin_url"><span class="admin-note-label">{{ copy('insightsLinkedin') }}</span> <a class="text-link" :href="inquiry.linkedin_url" target="_blank" rel="noopener">{{ inquiry.linkedin_url }} <ExternalLink :size="13" /></a></p>
                    <p class="admin-full whitespace-pre-line leading-6">{{ inquiry.message }}</p>
                </div>
            </article>

            <AppButton
                v-if="inquiriesHasMore"
                variant="secondary"
                size="sm"
                :disabled="inquiriesLoading"
                @click="$emit('load-more')"
            >
                {{ inquiriesLoading ? copy('insightsLoading') : copy('insightsLoadMore') }}
            </AppButton>
        </div>

        <div v-else-if="tab === 'news'" class="space-y-4">
            <div class="admin-note">{{ copy('insightsNewsInfo') }}</div>
            <p class="week-day-empty">{{ copy('underConstruction') }}</p>
        </div>

        <SecurityTab v-else :events="securityEvents" :loading="securityLoading" />
    </SectionTabs>
</template>
