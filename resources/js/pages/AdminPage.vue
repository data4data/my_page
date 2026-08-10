<script setup>
import { ref } from 'vue';
import { ArrowRight, LogOut, RefreshCcw } from '@lucide/vue';
import AppButton from '../components/ui/AppButton.vue';
import ProfileTab from './admin/ProfileTab.vue';
import MetricsTab from './admin/MetricsTab.vue';
import ExpertiseTab from './admin/ExpertiseTab.vue';
import ProcessTab from './admin/ProcessTab.vue';
import ProjectsTab from './admin/ProjectsTab.vue';
import InquiriesTab from './admin/InquiriesTab.vue';
import { copy } from '../shared/i18n';
import { csrfToken, usePortfolioSource } from '../shared/portfolio';

const {
    data,
    loading,
    fetchPortfolio,
    profile,
    metrics,
    expertise,
    projects,
    processSteps,
} = usePortfolioSource('/control-room-ao/portfolio');

const saving = ref(false);
const restoring = ref(false);
const message = ref('');
const tab = ref('profile');

const inquiries = ref([]);
const inquiriesLoading = ref(true);

const adminTabs = [
    { value: 'profile', label: 'Profile' },
    { value: 'metrics', label: 'Experience' },
    { value: 'expertise', label: 'Expertise' },
    { value: 'process', label: 'Process' },
    { value: 'projects', label: 'Projects' },
    { value: 'inquiries', label: 'Inquiries' },
];

const fetchInquiries = async () => {
    inquiriesLoading.value = true;
    const response = await fetch('/control-room-ao/inquiries');
    const body = await response.json();
    inquiries.value = body.inquiries ?? [];
    inquiriesLoading.value = false;
};

fetchPortfolio();
fetchInquiries();

const savePortfolio = async () => {
    saving.value = true;
    message.value = '';

    const response = await fetch('/control-room-ao/portfolio', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(data.value),
    });

    if (!response.ok) {
        message.value = copy('error');
        saving.value = false;
        return;
    }

    await fetchPortfolio();
    message.value = copy('saved');
    saving.value = false;
};

const restoreDefaults = async () => {
    restoring.value = true;
    message.value = '';

    const response = await fetch('/control-room-ao/portfolio/seed-defaults', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
    });

    if (!response.ok) {
        message.value = copy('error');
        restoring.value = false;
        return;
    }

    await fetchPortfolio();
    message.value = copy('restored');
    restoring.value = false;
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
    <main v-if="loading" class="min-h-screen bg-[#f4efe7] px-6 py-10 text-[#071523]">
        <div class="mx-auto max-w-7xl">{{ copy('loading') }}</div>
    </main>

    <main v-else class="min-h-screen bg-[#f4efe7] text-[#071523]">
        <header class="sticky top-0 z-30 border-b border-[#d8cbbb] bg-[#f4efe7]/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-4">
                <a href="/" class="text-3xl font-semibold tracking-normal">{{ profile.initials }}</a>
                <nav class="flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-[0.18em]">
                    <button v-for="item in adminTabs" :key="item.value" class="admin-tab" :class="{ active: tab === item.value }" @click="tab = item.value">
                        {{ item.label }}
                    </button>
                </nav>
                <div class="flex items-center gap-3">
                    <AppButton variant="primary" size="sm" :disabled="saving" @click="savePortfolio">
                        {{ saving ? copy('saving') : copy('save') }}
                        <ArrowRight :size="16" />
                    </AppButton>
                    <form method="POST" action="/logout">
                        <input type="hidden" name="_token" :value="csrfToken()">
                        <AppButton variant="secondary" size="sm" type="submit" :aria-label="copy('logout')">
                            <LogOut :size="16" />
                        </AppButton>
                    </form>
                </div>
            </div>
        </header>

        <section class="mx-auto grid max-w-7xl gap-6 px-5 py-8 lg:grid-cols-[280px_1fr]">
            <aside class="rounded-lg border border-[#d8cbbb] border-t-2 border-t-[#071523] bg-white/55 p-5">
                <p class="eyebrow">{{ copy('admin') }}</p>
                <h1 class="mt-3 font-serif text-4xl leading-tight">{{ copy('studio') }} {{ profile.initials }}</h1>
                <p class="mt-4 text-sm leading-6 text-[#516070]">{{ copy('studioCopy') }}</p>
                <p v-if="message" class="mt-5 rounded-md border border-[#b99a62]/40 bg-[#fff8ea] px-3 py-2 text-sm text-[#805d23]">{{ message }}</p>
                <AppButton variant="secondary" class="mt-5 w-full justify-center" :disabled="restoring" @click="restoreDefaults">
                    <RefreshCcw :size="16" />
                    {{ restoring ? copy('restoring') : copy('restore') }}
                </AppButton>
                <p class="mt-3 text-xs leading-5 text-[#7b6d5f]">{{ copy('restoreHint') }}</p>
            </aside>

            <div class="admin-panel">
                <ProfileTab v-if="tab === 'profile'" :profile="profile" />
                <MetricsTab v-else-if="tab === 'metrics'" :metrics="metrics" :add-item="addItem" :remove-item="removeItem" :move-item="moveItem" />
                <ExpertiseTab v-else-if="tab === 'expertise'" :expertise="expertise" :add-item="addItem" :remove-item="removeItem" :move-item="moveItem" />
                <ProcessTab v-else-if="tab === 'process'" :process-steps="processSteps" :add-item="addItem" :remove-item="removeItem" :move-item="moveItem" />
                <ProjectsTab v-else-if="tab === 'projects'" :projects="projects" :add-item="addItem" :remove-item="removeItem" :move-item="moveItem" :update-tags="updateTags" />
                <InquiriesTab v-else-if="tab === 'inquiries'" :inquiries="inquiries" :inquiries-loading="inquiriesLoading" />
            </div>
        </section>
    </main>
</template>
