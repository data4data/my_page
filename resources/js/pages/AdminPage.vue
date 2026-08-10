<script setup>
import { ref } from 'vue';
import { ArrowRight, ExternalLink, LogOut, Plus, RefreshCcw } from '@lucide/vue';
import AppButton from '../components/ui/AppButton.vue';
import AppInput from '../components/ui/AppInput.vue';
import AppTextarea from '../components/ui/AppTextarea.vue';
import AppSelect from '../components/ui/AppSelect.vue';
import AppCheckbox from '../components/ui/AppCheckbox.vue';
import EditableCard from '../components/EditableCard.vue';
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

const processGroupOptions = [
    { label: 'Input', value: 'input' },
    { label: 'Core', value: 'core' },
    { label: 'Output', value: 'output' },
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

const formatDate = (value) => new Date(value).toLocaleString(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

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
                <a href="/" class="text-3xl font-semibold tracking-normal">OA</a>
                <nav class="flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-[0.18em]">
                    <button v-for="item in adminTabs" :key="item.value" class="admin-tab" :class="{ active: tab === item.value }" @click="tab = item.value">
                        {{ item.label }}
                    </button>
                </nav>
                <div class="flex items-center gap-3">
                    <AppButton variant="primary" :disabled="saving" @click="savePortfolio">
                        {{ saving ? copy('saving') : copy('save') }}
                        <ArrowRight :size="16" />
                    </AppButton>
                    <form method="POST" action="/logout">
                        <input type="hidden" name="_token" :value="csrfToken()">
                        <AppButton variant="secondary" type="submit" :aria-label="copy('logout')">
                            <LogOut :size="16" />
                        </AppButton>
                    </form>
                </div>
            </div>
        </header>

        <section class="mx-auto grid max-w-7xl gap-6 px-5 py-8 lg:grid-cols-[280px_1fr]">
            <aside class="rounded-lg border border-[#d8cbbb] bg-white/55 p-5">
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
                <div v-if="tab === 'profile'" class="admin-grid">
                    <label class="admin-full">Initials<AppInput v-model="profile.initials" maxlength="12" /></label>
                    <label>Role EN<AppInput v-model="profile.role.en" /></label>
                    <label>Role NL<AppInput v-model="profile.role.nl" /></label>
                    <label>Headline EN<AppTextarea v-model="profile.headline.en" rows="2" /></label>
                    <label>Headline NL<AppTextarea v-model="profile.headline.nl" rows="2" /></label>
                    <label>Summary EN<AppTextarea v-model="profile.summary.en" rows="3" /></label>
                    <label>Summary NL<AppTextarea v-model="profile.summary.nl" rows="3" /></label>
                    <label>Primary CTA label EN<AppInput v-model="profile.primary_cta_label.en" /></label>
                    <label>Primary CTA label NL<AppInput v-model="profile.primary_cta_label.nl" /></label>
                    <label class="admin-full">Primary CTA URL<AppInput v-model="profile.primary_cta_url" /></label>
                    <label>Secondary CTA label EN<AppInput v-model="profile.secondary_cta_label.en" /></label>
                    <label>Secondary CTA label NL<AppInput v-model="profile.secondary_cta_label.nl" /></label>
                    <label class="admin-full">Secondary CTA URL<AppInput v-model="profile.secondary_cta_url" /></label>
                    <label>Location note EN<AppInput v-model="profile.location_note.en" /></label>
                    <label>Location note NL<AppInput v-model="profile.location_note.nl" /></label>
                    <label>Availability note EN<AppInput v-model="profile.availability_note.en" /></label>
                    <label>Availability note NL<AppInput v-model="profile.availability_note.nl" /></label>
                    <label>Quote text EN<AppTextarea v-model="profile.quote.en" rows="2" /></label>
                    <label>Quote text NL<AppTextarea v-model="profile.quote.nl" rows="2" /></label>
                    <label>Quote author EN<AppInput v-model="profile.quote_author.en" /></label>
                    <label>Quote author NL<AppInput v-model="profile.quote_author.nl" /></label>
                </div>

                <div v-if="tab === 'metrics'" class="space-y-4">
                    <p v-if="metrics.length === 0" class="admin-note">{{ copy('empty') }}</p>
                    <EditableCard v-for="(item, index) in metrics" :key="index" title="Metric" :index="index" collection="metrics" @move="moveItem" @remove="removeItem">
                        <label>Value<AppInput v-model="item.value" /></label>
                        <label>Label EN<AppInput v-model="item.label.en" /></label>
                        <label>Label NL<AppInput v-model="item.label.nl" /></label>
                        <AppCheckbox v-model="item.is_visible">Visible</AppCheckbox>
                    </EditableCard>
                    <AppButton variant="secondary" @click="addItem('metrics', { value: '1+', label: { en: 'New metric', nl: 'Nieuwe metriek' } })"><Plus :size="16" /> Add metric</AppButton>
                </div>

                <div v-if="tab === 'expertise'" class="space-y-4">
                    <div class="admin-note">
                        These items fill the public Expertise carousel and the skill gears. Change order, text, icon, visibility, and gear size here.
                    </div>
                    <p v-if="expertise.length === 0" class="admin-note">{{ copy('empty') }}</p>
                    <EditableCard v-for="(item, index) in expertise" :key="index" title="Expertise" :index="index" collection="expertise_items" @move="moveItem" @remove="removeItem">
                        <label>Title EN<AppInput v-model="item.title.en" /></label>
                        <label>Title NL<AppInput v-model="item.title.nl" /></label>
                        <label>Icon<AppInput v-model="item.icon" placeholder="code, link, settings, database" /></label>
                        <label>Category<AppInput v-model="item.category" /></label>
                        <label>Gear size<AppInput v-model.number="item.gear_size" type="number" min="82" max="180" step="2" /></label>
                        <AppCheckbox v-model="item.is_visible">Visible</AppCheckbox>
                        <label>Description EN<AppTextarea v-model="item.description.en" rows="2" /></label>
                        <label>Description NL<AppTextarea v-model="item.description.nl" rows="2" /></label>
                    </EditableCard>
                    <AppButton variant="secondary" @click="addItem('expertise_items', { title: { en: 'New expertise', nl: 'Nieuwe expertise' }, description: { en: 'Describe the result and capability.', nl: 'Beschrijf het resultaat en de expertise.' }, icon: 'sparkles', category: 'general', gear_size: 120 })"><Plus :size="16" /> Add expertise</AppButton>
                </div>

                <div v-if="tab === 'process'" class="space-y-4">
                    <EditableCard v-for="(item, index) in processSteps" :key="index" title="Process step" :index="index" collection="process_steps" @move="moveItem" @remove="removeItem">
                        <label>Group<AppSelect v-model="item.group" :options="processGroupOptions" /></label>
                        <label>Title EN<AppInput v-model="item.title.en" /></label>
                        <label>Title NL<AppInput v-model="item.title.nl" /></label>
                        <label>Icon<AppInput v-model="item.icon" /></label>
                        <AppCheckbox v-model="item.is_visible">Visible</AppCheckbox>
                        <label>Description EN<AppTextarea v-model="item.description.en" rows="2" /></label>
                        <label>Description NL<AppTextarea v-model="item.description.nl" rows="2" /></label>
                    </EditableCard>
                    <AppButton variant="secondary" @click="addItem('process_steps', { group: 'core', title: { en: 'New step', nl: 'Nieuwe stap' }, description: { en: 'Short description', nl: 'Korte beschrijving' }, icon: 'sparkles' })"><Plus :size="16" /> Add process step</AppButton>
                </div>

                <div v-if="tab === 'projects'" class="space-y-4">
                    <EditableCard v-for="(item, index) in projects" :key="index" title="Project" :index="index" collection="projects" @move="moveItem" @remove="removeItem">
                        <label>Title EN<AppInput v-model="item.title.en" /></label>
                        <label>Title NL<AppInput v-model="item.title.nl" /></label>
                        <label>Visual style<AppInput v-model="item.visual_style" placeholder="dashboard, flow, cms" /></label>
                        <label>Summary EN<AppTextarea v-model="item.summary.en" rows="2" /></label>
                        <label>Summary NL<AppTextarea v-model="item.summary.nl" rows="2" /></label>
                        <label>Result EN<AppInput v-model="item.result.en" /></label>
                        <label>Result NL<AppInput v-model="item.result.nl" /></label>
                        <label>Tags<AppInput :model-value="item.tags?.join(', ')" @update:model-value="(value) => updateTags(item, value)" /></label>
                        <AppCheckbox v-model="item.is_visible">Visible</AppCheckbox>
                    </EditableCard>
                    <AppButton variant="secondary" @click="addItem('projects', { title: { en: 'New project', nl: 'Nieuw project' }, summary: { en: 'Describe the system and result.', nl: 'Beschrijf het systeem en resultaat.' }, result: { en: 'What improved.', nl: 'Wat is verbeterd.' }, tags: ['Laravel'], visual_style: 'dashboard' })"><Plus :size="16" /> Add project</AppButton>
                </div>

                <div v-if="tab === 'inquiries'" class="space-y-4">
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
            </div>
        </section>
    </main>
</template>
