<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { ArrowRight, ChevronLeft, ChevronRight } from '@lucide/vue';
import AppButton from '../components/ui/AppButton.vue';
import DeveloperConnectModal from '../components/DeveloperConnectModal.vue';
import { resolveIcon } from '../shared/icons';
import { copy, lang, setLang, t } from '../shared/i18n';
import { usePortfolioSource } from '../shared/portfolio';

const route = useRoute();
const showConnectModal = computed(() => route.name === 'hi-developer');

const {
    loading,
    fetchPortfolio,
    profile,
    metrics,
    expertise,
    projects,
    processSteps,
    socialLinks,
} = usePortfolioSource('/portfolio');

const headerScrolled = ref(false);
const expertiseCarousel = ref(null);
const expertiseIndex = ref(0);

const inputs = computed(() => processSteps.value.filter((item) => item.group === 'input'));
const core = computed(() => processSteps.value.filter((item) => item.group === 'core'));
const outputs = computed(() => processSteps.value.filter((item) => item.group === 'output'));

const Icon = (name) => resolveIcon(name);

const headlineSegments = computed(() => {
    const text = t(profile.value.headline);
    const pattern = /(precision|precisie|impact)/gi;

    return text.split(pattern).filter(Boolean).map((part) => ({
        text: part,
        tone: /^(precision|precisie)$/i.test(part) ? 'blue' : (/^impact$/i.test(part) ? 'gold' : 'default'),
    }));
});

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
                    <AppButton variant="lang" :active="lang === 'en'" @click="setLang('en')">EN</AppButton>
                    <AppButton variant="lang" :active="lang === 'nl'" @click="setLang('nl')">NL</AppButton>
                    <AppButton variant="menu" as="router-link" :to="{ name: 'hi-developer' }">
                        {{ copy('forDevelopers') }}
                    </AppButton>
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
                        <AppButton variant="primary" as="a" :href="profile.primary_cta_url">{{ t(profile.primary_cta_label) }} <ArrowRight :size="17" /></AppButton>
                        <AppButton variant="link" as="a" :href="profile.secondary_cta_url">{{ t(profile.secondary_cta_label) }} <ArrowRight :size="17" /></AppButton>
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
        </footer>

        <DeveloperConnectModal v-if="showConnectModal" />
    </main>
</template>
