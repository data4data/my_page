<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    ArrowRight,
    BarChart3,
    Box,
    ChevronLeft,
    ChevronRight,
    Cloud,
    Code2,
    Database,
    ExternalLink,
    GitBranch,
    Globe2,
    Link as LinkIcon,
    Mail,
    Monitor,
    Plus,
    RefreshCcw,
    Settings,
    Sparkles,
    Target,
    Users,
    Zap,
} from '@lucide/vue';
import EditableCard from './components/EditableCard.vue';

const iconMap = {
    box: Box,
    chart: BarChart3,
    cloud: Cloud,
    code: Code2,
    database: Database,
    github: GitBranch,
    globe: Globe2,
    linkedin: ExternalLink,
    link: LinkIcon,
    mail: Mail,
    monitor: Monitor,
    settings: Settings,
    sparkles: Sparkles,
    target: Target,
    users: Users,
    zap: Zap,
};

const isAdmin = window.location.pathname.startsWith('/admin');
const loading = ref(true);
const saving = ref(false);
const restoring = ref(false);
const message = ref('');
const tab = ref('profile');
const headerScrolled = ref(false);
const expertiseCarousel = ref(null);
const expertiseIndex = ref(0);
const adminTabs = [
    { value: 'profile', label: 'Profile' },
    { value: 'metrics', label: 'Experience' },
    { value: 'expertise', label: 'Expertise' },
    { value: 'process', label: 'Process' },
    { value: 'projects', label: 'Projects' },
];
const lang = ref(localStorage.getItem('oa-language') || 'en');
const data = ref(null);

const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const translatableProfile = ['role', 'headline', 'summary', 'primary_cta_label', 'secondary_cta_label', 'location_note', 'availability_note', 'quote', 'quote_author'];
const translatableItemFields = {
    metrics: ['label'],
    expertise_items: ['title', 'description'],
    projects: ['title', 'summary', 'result'],
    process_steps: ['title', 'description'],
};

const ui = {
    en: {
        loading: 'Loading OA page...',
        admin: 'Admin',
        work: 'Projects',
        about: 'About',
        expertise: 'Expertise',
        contact: 'Contact',
        scroll: 'Scroll',
        railCta: 'Explore more',
        how: 'How I work',
        fromComplex: 'From complex',
        toSimple: 'to simple.',
        processCopy: 'I break down complexity into clear systems that are easy to use, reliable, and built to scale.',
        architecture: 'System Architecture',
        processDetails: 'Process description',
        featured: 'Featured projects',
        viewAll: 'View GitHub projects',
        quote: 'Simplicity is the ultimate sophistication.',
        contactHeadline: 'Let’s build something exceptional together.',
        contactHeadlineLines: ['Let’s build something', 'exceptional', 'together.'],
        getInTouch: 'Get in touch',
        save: 'Save changes',
        saving: 'Saving...',
        saved: 'Saved. Public page updated.',
        restore: 'Restore default content',
        restoring: 'Restoring...',
        restored: 'Default content restored. You can edit and save it now.',
        restoreHint: 'This replaces the current admin content with the seeded OA defaults.',
        empty: 'No items yet. Add one manually or restore the seeded defaults.',
        error: 'Could not save changes. Please check the fields and try again.',
        studio: 'Content studio for',
        studioCopy: 'Edit the bilingual visit-card content without touching code. Items save in the order shown here.',
    },
    nl: {
        loading: 'OA-pagina laden...',
        admin: 'Admin',
        work: 'Projecten',
        about: 'Over mij',
        expertise: 'Expertise',
        contact: 'Contact',
        scroll: 'Scroll',
        railCta: 'Ontdek meer',
        how: 'Zo werk ik',
        fromComplex: 'Van complex',
        toSimple: 'naar simpel.',
        processCopy: 'Ik breng complexiteit terug naar duidelijke systemen die prettig werken, betrouwbaar zijn en kunnen meegroeien.',
        architecture: 'Systeemarchitectuur',
        processDetails: 'Procesbeschrijving',
        featured: 'Uitgelichte projecten',
        viewAll: 'Bekijk GitHub-projecten',
        quote: 'Eenvoud is de ultieme verfijning.',
        contactHeadline: 'Laten we iets uitzonderlijks bouwen.',
        contactHeadlineLines: ['Laten we iets', 'uitzonderlijks', 'bouwen.'],
        getInTouch: 'Neem contact op',
        save: 'Wijzigingen opslaan',
        saving: 'Opslaan...',
        saved: 'Opgeslagen. De publieke pagina is bijgewerkt.',
        restore: 'Standaardcontent herstellen',
        restoring: 'Herstellen...',
        restored: 'Standaardcontent is hersteld. Je kunt deze nu bewerken en opslaan.',
        restoreHint: 'Dit vervangt de huidige admincontent door de standaard OA-seeddata.',
        empty: 'Nog geen items. Voeg er een toe of herstel de standaard seeddata.',
        error: 'Opslaan is niet gelukt. Controleer de velden en probeer opnieuw.',
        studio: 'Contentstudio voor',
        studioCopy: 'Bewerk de tweetalige visit-card content zonder code aan te raken. Items worden opgeslagen in de volgorde die je hier ziet.',
    },
};

const fetchPortfolio = async () => {
    const response = await fetch(isAdmin ? '/admin/portfolio' : '/portfolio');
    data.value = await response.json();
    normalizeData();
    loading.value = false;
};

const updateHeaderState = () => {
    headerScrolled.value = window.scrollY > 8;
};

onMounted(() => {
    fetchPortfolio();
    updateHeaderState();
    window.addEventListener('scroll', updateHeaderState, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', updateHeaderState);
});

const profile = computed(() => data.value?.profile ?? {});
const metrics = computed(() => data.value?.metrics ?? []);
const expertise = computed(() => data.value?.expertise_items ?? []);
const projects = computed(() => data.value?.projects ?? []);
const processSteps = computed(() => data.value?.process_steps ?? []);
const inputs = computed(() => processSteps.value.filter((item) => item.group === 'input'));
const core = computed(() => processSteps.value.filter((item) => item.group === 'core'));
const outputs = computed(() => processSteps.value.filter((item) => item.group === 'output'));
const socialLinks = computed(() => Array.isArray(profile.value.social_links) ? profile.value.social_links : []);

const Icon = (name) => iconMap[name] ?? Sparkles;
const copy = (key) => ui[lang.value][key] ?? ui.en[key] ?? key;
const t = (value) => {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        return value[lang.value] || value.en || value.nl || '';
    }

    return value ?? '';
};

const headlineSegments = computed(() => {
    const text = t(profile.value.headline);
    const pattern = /(precision|precisie|impact)/gi;

    return text.split(pattern).filter(Boolean).map((part) => ({
        text: part,
        tone: /^(precision|precisie)$/i.test(part) ? 'blue' : (/^impact$/i.test(part) ? 'gold' : 'default'),
    }));
});

const setLang = (value) => {
    lang.value = value;
    localStorage.setItem('oa-language', value);
};

const asTranslation = (value) => {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        return { en: value.en ?? '', nl: value.nl ?? value.en ?? '' };
    }

    return { en: value ?? '', nl: value ?? '' };
};

const normalizeData = () => {
    ['metrics', 'expertise_items', 'projects', 'process_steps'].forEach((collection) => {
        const profileItems = data.value.profile?.[collection];

        if ((!Array.isArray(data.value[collection]) || data.value[collection].length === 0) && Array.isArray(profileItems) && profileItems.length > 0) {
            data.value[collection] = profileItems;
        }

        if (!Array.isArray(data.value[collection])) {
            data.value[collection] = [];
        }
    });

    translatableProfile.forEach((field) => {
        data.value.profile[field] = asTranslation(data.value.profile[field]);
    });

    Object.entries(translatableItemFields).forEach(([collection, fields]) => {
        data.value[collection]?.forEach((item) => {
            fields.forEach((field) => {
                item[field] = asTranslation(item[field]);
            });

            if (collection === 'expertise_items') {
                item.gear_size = Number(item.gear_size) || 120;
            }
        });
    });
};

const savePortfolio = async () => {
    saving.value = true;
    message.value = '';

    const response = await fetch('/admin/portfolio', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify(data.value),
    });

    if (!response.ok) {
        message.value = copy('error');
        saving.value = false;
        return;
    }

    data.value = await response.json();
    normalizeData();
    message.value = copy('saved');
    saving.value = false;
};

const restoreDefaults = async () => {
    restoring.value = true;
    message.value = '';

    const response = await fetch('/admin/portfolio/seed-defaults', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
        },
    });

    if (!response.ok) {
        message.value = copy('error');
        restoring.value = false;
        return;
    }

    data.value = await response.json();
    normalizeData();
    expertiseIndex.value = 0;
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

const scrollExpertise = (direction) => {
    const track = expertiseCarousel.value;
    const items = track ? Array.from(track.children) : [];

    if (!track || items.length === 0) {
        return;
    }

    expertiseIndex.value = (expertiseIndex.value + direction + items.length) % items.length;

    track.scrollTo({
        left: items[expertiseIndex.value].offsetLeft,
        behavior: 'smooth',
    });
};
</script>

<template>
    <main v-if="loading" class="min-h-screen bg-[#f4efe7] px-6 py-10 text-[#071523]">
        <div class="mx-auto max-w-7xl">{{ copy('loading') }}</div>
    </main>

    <main v-else-if="isAdmin" class="min-h-screen bg-[#f4efe7] text-[#071523]">
        <header class="sticky top-0 z-30 border-b border-[#d8cbbb] bg-[#f4efe7]/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-4">
                <a href="/" class="text-3xl font-semibold tracking-normal">OA</a>
                <nav class="flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-[0.18em]">
                    <button v-for="item in adminTabs" :key="item.value" class="admin-tab" :class="{ active: tab === item.value }" @click="tab = item.value">
                        {{ item.label }}
                    </button>
                </nav>
                <button class="dark-button" :disabled="saving" @click="savePortfolio">
                    {{ saving ? copy('saving') : copy('save') }}
                    <ArrowRight :size="16" />
                </button>
            </div>
        </header>

        <section class="mx-auto grid max-w-7xl gap-6 px-5 py-8 lg:grid-cols-[280px_1fr]">
            <aside class="rounded-lg border border-[#d8cbbb] bg-white/55 p-5">
                <p class="eyebrow">{{ copy('admin') }}</p>
                <h1 class="mt-3 font-serif text-4xl leading-tight">{{ copy('studio') }} {{ profile.initials }}</h1>
                <p class="mt-4 text-sm leading-6 text-[#516070]">{{ copy('studioCopy') }}</p>
                <p v-if="message" class="mt-5 rounded-md border border-[#b99a62]/40 bg-[#fff8ea] px-3 py-2 text-sm text-[#805d23]">{{ message }}</p>
                <button class="light-button mt-5 w-full justify-center" :disabled="restoring" @click="restoreDefaults">
                    <RefreshCcw :size="16" />
                    {{ restoring ? copy('restoring') : copy('restore') }}
                </button>
                <p class="mt-3 text-xs leading-5 text-[#7b6d5f]">{{ copy('restoreHint') }}</p>
            </aside>

            <div class="admin-panel">
                <div v-if="tab === 'profile'" class="admin-grid">
                    <label class="admin-full">Initials<input v-model="profile.initials" maxlength="12"></label>
                    <label>Role EN<input v-model="profile.role.en"></label>
                    <label>Role NL<input v-model="profile.role.nl"></label>
                    <label>Headline EN<textarea v-model="profile.headline.en" rows="2"></textarea></label>
                    <label>Headline NL<textarea v-model="profile.headline.nl" rows="2"></textarea></label>
                    <label>Summary EN<textarea v-model="profile.summary.en" rows="3"></textarea></label>
                    <label>Summary NL<textarea v-model="profile.summary.nl" rows="3"></textarea></label>
                    <label>Primary CTA label EN<input v-model="profile.primary_cta_label.en"></label>
                    <label>Primary CTA label NL<input v-model="profile.primary_cta_label.nl"></label>
                    <label class="admin-full">Primary CTA URL<input v-model="profile.primary_cta_url"></label>
                    <label>Secondary CTA label EN<input v-model="profile.secondary_cta_label.en"></label>
                    <label>Secondary CTA label NL<input v-model="profile.secondary_cta_label.nl"></label>
                    <label class="admin-full">Secondary CTA URL<input v-model="profile.secondary_cta_url"></label>
                    <label>Location note EN<input v-model="profile.location_note.en"></label>
                    <label>Location note NL<input v-model="profile.location_note.nl"></label>
                    <label>Availability note EN<input v-model="profile.availability_note.en"></label>
                    <label>Availability note NL<input v-model="profile.availability_note.nl"></label>
                    <label>Quote text EN<textarea v-model="profile.quote.en" rows="2"></textarea></label>
                    <label>Quote text NL<textarea v-model="profile.quote.nl" rows="2"></textarea></label>
                    <label>Quote author EN<input v-model="profile.quote_author.en"></label>
                    <label>Quote author NL<input v-model="profile.quote_author.nl"></label>
                </div>

                <div v-if="tab === 'metrics'" class="space-y-4">
                    <p v-if="metrics.length === 0" class="admin-note">{{ copy('empty') }}</p>
                    <EditableCard v-for="(item, index) in metrics" :key="index" title="Metric" :index="index" collection="metrics" @move="moveItem" @remove="removeItem">
                        <label>Value<input v-model="item.value"></label>
                        <label>Label EN<input v-model="item.label.en"></label>
                        <label>Label NL<input v-model="item.label.nl"></label>
                        <label class="check"><input v-model="item.is_visible" type="checkbox"> Visible</label>
                    </EditableCard>
                    <button class="light-button" @click="addItem('metrics', { value: '1+', label: { en: 'New metric', nl: 'Nieuwe metriek' } })"><Plus :size="16" /> Add metric</button>
                </div>

                <div v-if="tab === 'expertise'" class="space-y-4">
                    <div class="admin-note">
                        These items fill the public Expertise carousel and the skill gears. Change order, text, icon, visibility, and gear size here.
                    </div>
                    <p v-if="expertise.length === 0" class="admin-note">{{ copy('empty') }}</p>
                    <EditableCard v-for="(item, index) in expertise" :key="index" title="Expertise" :index="index" collection="expertise_items" @move="moveItem" @remove="removeItem">
                        <label>Title EN<input v-model="item.title.en"></label>
                        <label>Title NL<input v-model="item.title.nl"></label>
                        <label>Icon<input v-model="item.icon" placeholder="code, link, settings, database"></label>
                        <label>Category<input v-model="item.category"></label>
                        <label>Gear size<input v-model.number="item.gear_size" type="number" min="82" max="180" step="2"></label>
                        <label class="check"><input v-model="item.is_visible" type="checkbox"> Visible</label>
                        <label>Description EN<textarea v-model="item.description.en" rows="2"></textarea></label>
                        <label>Description NL<textarea v-model="item.description.nl" rows="2"></textarea></label>
                    </EditableCard>
                    <button class="light-button" @click="addItem('expertise_items', { title: { en: 'New expertise', nl: 'Nieuwe expertise' }, description: { en: 'Describe the result and capability.', nl: 'Beschrijf het resultaat en de expertise.' }, icon: 'sparkles', category: 'general', gear_size: 120 })"><Plus :size="16" /> Add expertise</button>
                </div>

                <div v-if="tab === 'process'" class="space-y-4">
                    <EditableCard v-for="(item, index) in processSteps" :key="index" title="Process step" :index="index" collection="process_steps" @move="moveItem" @remove="removeItem">
                        <label>Group
                            <select v-model="item.group">
                                <option value="input">Input</option>
                                <option value="core">Core</option>
                                <option value="output">Output</option>
                            </select>
                        </label>
                        <label>Title EN<input v-model="item.title.en"></label>
                        <label>Title NL<input v-model="item.title.nl"></label>
                        <label>Icon<input v-model="item.icon"></label>
                        <label class="check"><input v-model="item.is_visible" type="checkbox"> Visible</label>
                        <label>Description EN<textarea v-model="item.description.en" rows="2"></textarea></label>
                        <label>Description NL<textarea v-model="item.description.nl" rows="2"></textarea></label>
                    </EditableCard>
                    <button class="light-button" @click="addItem('process_steps', { group: 'core', title: { en: 'New step', nl: 'Nieuwe stap' }, description: { en: 'Short description', nl: 'Korte beschrijving' }, icon: 'sparkles' })"><Plus :size="16" /> Add process step</button>
                </div>

                <div v-if="tab === 'projects'" class="space-y-4">
                    <EditableCard v-for="(item, index) in projects" :key="index" title="Project" :index="index" collection="projects" @move="moveItem" @remove="removeItem">
                        <label>Title EN<input v-model="item.title.en"></label>
                        <label>Title NL<input v-model="item.title.nl"></label>
                        <label>Visual style<input v-model="item.visual_style" placeholder="dashboard, flow, cms"></label>
                        <label>Summary EN<textarea v-model="item.summary.en" rows="2"></textarea></label>
                        <label>Summary NL<textarea v-model="item.summary.nl" rows="2"></textarea></label>
                        <label>Result EN<input v-model="item.result.en"></label>
                        <label>Result NL<input v-model="item.result.nl"></label>
                        <label>Tags<input :value="item.tags?.join(', ')" @input="updateTags(item, $event.target.value)"></label>
                        <label class="check"><input v-model="item.is_visible" type="checkbox"> Visible</label>
                    </EditableCard>
                    <button class="light-button" @click="addItem('projects', { title: { en: 'New project', nl: 'Nieuw project' }, summary: { en: 'Describe the system and result.', nl: 'Beschrijf het systeem en resultaat.' }, result: { en: 'What improved.', nl: 'Wat is verbeterd.' }, tags: ['Laravel'], visual_style: 'dashboard' })"><Plus :size="16" /> Add project</button>
                </div>
            </div>
        </section>
    </main>

    <main v-else class="min-h-screen overflow-hidden bg-[#f4efe7] text-[#071523]">
        <header class="site-header" :class="{ scrolled: headerScrolled }">
            <div class="site-header-inner">
                <a href="#" class="site-logo">{{ profile.initials }}</a>
                <nav class="site-nav">
                    <a href="#work">{{ copy('work') }}</a>
                    <a href="#about">{{ copy('about') }}</a>
                    <a href="#expertise">{{ copy('expertise') }}</a>
                    <a href="#contact">{{ copy('contact') }}</a>
                </nav>
                <div class="site-actions">
                    <button class="lang-button" :class="{ active: lang === 'en' }" @click="setLang('en')">EN</button>
                    <button class="lang-button" :class="{ active: lang === 'nl' }" @click="setLang('nl')">NL</button>
                    <a href="/admin" class="menu-button">
                        Menu
                    </a>
                </div>
            </div>
        </header>

        <section class="hero-shell">
            <div class="hero-visual" aria-hidden="true">
                <img class="hero-photo" :src="'/images/header-hero.png'" alt="">
                <div class="hero-photo-shade"></div>
            </div>

            <aside class="social-rail" aria-label="Social links">
                <span class="rail-role">{{ copy('railCta') }}</span>
                <div class="rail-line"></div>
                <a v-for="link in socialLinks" :key="link.label" :href="link.url" :aria-label="link.label">
                    <component :is="Icon(link.icon)" :size="20" />
                </a>
            </aside>

            <div class="hero-inner">
                <div class="hero-copy">
                    <p class="eyebrow">{{ t(profile.role) }}</p>
                    <h1 class="hero-title">
                        <span v-for="(segment, index) in headlineSegments" :key="`${segment.text}-${index}`" :class="`headline-${segment.tone}`">{{ segment.text }}</span>
                    </h1>
                    <p class="hero-summary">{{ t(profile.summary) }}</p>
                    <div class="hero-actions">
                        <a class="dark-button" :href="profile.primary_cta_url">{{ t(profile.primary_cta_label) }} <ArrowRight :size="17" /></a>
                        <a class="text-link" :href="profile.secondary_cta_url">{{ t(profile.secondary_cta_label) }} <ArrowRight :size="17" /></a>
                    </div>
                </div>

                <aside class="hero-scroll">
                    <div class="vertical-label">{{ copy('scroll') }}</div>
                </aside>
            </div>

            <div class="hero-stats">
                <div v-for="metric in metrics" :key="t(metric.label)" class="metric">
                    <strong>{{ metric.value }}</strong>
                    <span>{{ t(metric.label) }}</span>
                </div>
            </div>
        </section>

        <section id="expertise" class="page-section expertise-section mx-auto max-w-7xl px-5">
            <div class="expertise-carousel">
                <button class="expertise-arrow expertise-arrow-left" type="button" aria-label="Previous expertise" @click="scrollExpertise(-1)">
                    <ChevronLeft :size="22" />
                </button>
                <div ref="expertiseCarousel" class="expertise-track">
                    <article v-for="item in expertise" :key="t(item.title)" class="expertise-card">
                        <component :is="Icon(item.icon)" :size="30" />
                        <h2>{{ t(item.title) }}</h2>
                        <p>{{ t(item.description) }}</p>
                        <ArrowRight :size="18" class="ml-auto mt-auto" />
                    </article>
                </div>
                <button class="expertise-arrow expertise-arrow-right" type="button" aria-label="Next expertise" @click="scrollExpertise(1)">
                    <ChevronRight :size="22" />
                </button>
            </div>
        </section>

        <section id="about" class="page-section mx-auto max-w-7xl px-5">
            <div class="section-head">
                <p class="eyebrow">{{ copy('how') }}</p>
            </div>
            <div class="process-panel">
                <div class="process-visual">
                    <div class="process-visual-image"></div>
                    <div class="process-flow-card process-flow-card-input">
                        <strong>Input</strong>
                        <ul>
                            <li v-for="item in inputs" :key="t(item.title)">{{ t(item.title) }}</li>
                        </ul>
                    </div>
                    <div class="process-flow-card process-flow-card-output">
                        <strong>Output</strong>
                        <ul>
                            <li v-for="item in outputs" :key="t(item.title)">{{ t(item.title) }}</li>
                        </ul>
                    </div>
                </div>

                <div class="process-content">
                    <div class="process-overview">
                        <span>{{ copy('architecture') }}</span>
                        <h2>{{ copy('fromComplex') }}<br><em>{{ copy('toSimple') }}</em></h2>
                        <p>{{ copy('processCopy') }}</p>
                    </div>

                    <div class="process-expertise">
                        <strong>{{ copy('processDetails') }}</strong>
                        <div class="process-expertise-grid">
                            <article v-for="item in core" :key="t(item.title)">
                                <component :is="Icon(item.icon)" :size="20" />
                                <div>
                                    <h3>{{ t(item.title) }}</h3>
                                    <p>{{ t(item.description) }}</p>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="work" class="page-section mx-auto max-w-7xl px-5">
            <div class="section-head">
                <p class="eyebrow">{{ copy('featured') }}</p>
                <a href="#contact" class="text-link">{{ copy('viewAll') }} <ArrowRight :size="17" /></a>
            </div>
            <div class="project-grid">
                <article v-for="(project, index) in projects" :key="t(project.title)" class="project-card">
                    <div class="project-copy">
                        <span>{{ String(index + 1).padStart(2, '0') }}</span>
                        <h2>{{ t(project.title) }}</h2>
                        <p>{{ t(project.summary) }}</p>
                        <strong>{{ t(project.result) }}</strong>
                        <div class="tags">
                            <span v-for="tag in project.tags" :key="tag">{{ tag }}</span>
                        </div>
                    </div>
                    <div class="project-visual" :class="project.visual_style">
                        <span v-for="dot in 9" :key="dot"></span>
                    </div>
                </article>
            </div>
        </section>

        <section id="contact" class="page-section contact-section mx-auto max-w-7xl px-5">
            <div class="quote-card">
                <span>“</span>
                <p>{{ t(profile.quote) || copy('quote') }}</p>
                <strong>{{ t(profile.quote_author) }}</strong>
            </div>
            <div class="contact-band">
                <div class="contact-image" aria-hidden="true"></div>
                <div class="contact-copy">
                    <h2 class="contact-headline">
                        <span v-for="line in copy('contactHeadlineLines')" :key="line">{{ line }}</span>
                    </h2>
                    <a class="contact-cta-link" href="mailto:hello@example.com">{{ copy('getInTouch') }} <ArrowRight :size="17" /></a>
                </div>
            </div>
        </section>

        <footer class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-5 px-5 py-10 text-xs font-semibold uppercase tracking-[0.18em]">
            <span>{{ t(profile.location_note) }}</span>
            <span>{{ t(profile.availability_note) }}</span>
            <a href="/admin">{{ copy('admin') }}</a>
        </footer>
    </main>
</template>
