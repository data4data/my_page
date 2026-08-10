<script setup>
import { ExternalLink } from '@lucide/vue';

defineProps({
    inquiries: {
        type: Array,
        required: true,
    },
    inquiriesLoading: {
        type: Boolean,
        required: true,
    },
});

const formatDate = (value) => new Date(value).toLocaleString(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});
</script>

<template>
    <div class="space-y-4">
        <div class="admin-note">
            Submissions from the public "For developers" connect form. View-only — there is nothing here to edit or delete.
        </div>
        <p v-if="inquiriesLoading" class="admin-note">Loading...</p>
        <p v-else-if="inquiries.length === 0" class="admin-note">No messages yet.</p>
        <article v-for="inquiry in inquiries" :key="inquiry.id" class="editable-card">
            <header>
                <strong>{{ inquiry.name }}</strong>
                <span class="text-xs font-normal normal-case text-[#7b6d5f]">{{ formatDate(inquiry.created_at) }}</span>
            </header>
            <div class="admin-grid text-sm normal-case">
                <p><span class="admin-note-label">Email</span> <a class="text-link" :href="`mailto:${inquiry.email}`">{{ inquiry.email }}</a></p>
                <p v-if="inquiry.company"><span class="admin-note-label">Company</span> {{ inquiry.company }}</p>
                <p v-if="inquiry.portfolio_url"><span class="admin-note-label">Portfolio</span> <a class="text-link" :href="inquiry.portfolio_url" target="_blank" rel="noopener">{{ inquiry.portfolio_url }} <ExternalLink :size="13" /></a></p>
                <p v-if="inquiry.linkedin_url"><span class="admin-note-label">LinkedIn</span> <a class="text-link" :href="inquiry.linkedin_url" target="_blank" rel="noopener">{{ inquiry.linkedin_url }} <ExternalLink :size="13" /></a></p>
                <p class="admin-full whitespace-pre-line leading-6">{{ inquiry.message }}</p>
            </div>
        </article>
    </div>
</template>
