<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { ArrowRight, ChevronLeft, ChevronRight } from '@lucide/vue';
import AppButton from '../../components/ui/AppButton.vue';
import DeveloperConnectModal from '../../components/DeveloperConnectModal.vue';
import { resolveIcon } from '../../shared/icons';
import { applyLanguagePolicy, copy, lang, LANGUAGES, languageSwitcherShown, setLang, t } from '../../shared/i18n';
import { usePortfolioSource } from '../../shared/portfolio';

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
    railLinks,
    footerLinks,
} = usePortfolioSource('/portfolio');

const headerScrolled = ref(false);
const expertiseCarousel = ref(null);
const expertiseIndex = ref(0);
const activeSection = ref('');
// Document order (top to bottom), for the scan in updateActiveSection below.
const sectionIdsInDomOrder = ['expertise', 'about', 'work', 'contact'];
let sectionElements = [];

const inputs = computed(() => processSteps.value.filter((item) => item.group === 'input'));
const core = computed(() => processSteps.value.filter((item) => item.group === 'core'));
const outputs = computed(() => processSteps.value.filter((item) => item.group === 'output'));

const Icon = (name) => resolveIcon(name);

const escapeForRegex = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

// Which words in the headline take an accent colour comes from the profile
// (admin: Profile tab), not from a pattern in here. It used to be a literal
// /(precision|precisie|impact)/ matching one person's copy, so editing the
// headline silently lost the accent and no other headline could gain one.
const headlineSegments = computed(() => {
    const text = t(profile.value.headline);
    const highlights = Array.isArray(profile.value.headline_highlights) ? profile.value.headline_highlights : [];
    const terms = highlights.filter((item) => item?.text);

    if (terms.length === 0) {
        return text ? [{ text, tone: 'default' }] : [];
    }

    // Longest first, so "precision engineering" wins over a bare "precision"
    // when both are configured.
    const ordered = [...terms].sort((a, b) => b.text.length - a.text.length);
    const pattern = new RegExp(`(${ordered.map((item) => escapeForRegex(item.text)).join('|')})`, 'gi');

    const toneFor = (part) => ordered.find((item) => item.text.toLowerCase() === part.toLowerCase())?.tone ?? 'default';

    return text.split(pattern).filter(Boolean).map((part) => ({
        text: part,
        tone: toneFor(part),
    }));
});

const updateHeaderState = () => {
    headerScrolled.value = window.scrollY > 8;
};

// Highlights whichever section's top edge most recently crossed the
// reference line. A plain scan rather than IntersectionObserver, since
// overlapping/short sections can fire entries out of order there.
const updateActiveSection = () => {
    const referenceY = window.innerHeight * 0.35;
    let current = '';

    for (const section of sectionElements) {
        if (section.getBoundingClientRect().top <= referenceY) {
            current = section.id;
        }
    }

    activeSection.value = current;
};

// Hidden when the owner has turned the switcher off in the admin's Language
// tab — the site then runs in the default language only.
const showLanguageSwitcher = computed(() => languageSwitcherShown(profile.value));

onMounted(async () => {
    await fetchPortfolio();
    applyLanguagePolicy(profile.value);

    updateHeaderState();
    window.addEventListener('scroll', updateHeaderState, { passive: true });

    await nextTick();
    sectionElements = sectionIdsInDomOrder
        .map((id) => document.getElementById(id))
        .filter(Boolean);
    updateActiveSection();
    window.addEventListener('scroll', updateActiveSection, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', updateHeaderState);
    window.removeEventListener('scroll', updateActiveSection);
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
    <main v-if="loading" class="min-h-screen bg-cream px-6 py-10 text-ink">
        <div class="mx-auto max-w-7xl">{{ copy('loading') }}</div>
    </main>

    <!-- No bg-cream here: body already paints it, and an opaque background on
         this element would cover the ambient body::before wash (negative
         z-index paints beneath in-flow block backgrounds). -->
    <main v-else class="page-grid min-h-screen text-ink">
        <header class="site-header u-full" :class="{ scrolled: headerScrolled }">
            <div class="site-header-inner">
                <a href="#" class="site-logo">{{ profile.initials }}</a>
                <nav class="site-nav">
                    <a href="#work" :class="{ active: activeSection === 'work' }">{{ copy('work') }}</a>
                    <a href="#about" :class="{ active: activeSection === 'about' }">{{ copy('about') }}</a>
                    <a href="#expertise" :class="{ active: activeSection === 'expertise' }">{{ copy('expertise') }}</a>
                    <a href="#contact" :class="{ active: activeSection === 'contact' }">{{ copy('contact') }}</a>
                </nav>
                <div class="site-actions">
                    <template v-if="showLanguageSwitcher">
                        <AppButton
                            v-for="language in LANGUAGES"
                            :key="language.value"
                            variant="lang"
                            :active="lang === language.value"
                            @click="setLang(language.value)"
                        >
                            {{ language.value.toUpperCase() }}
                        </AppButton>
                    </template>
                    <AppButton variant="menu" as="router-link" :to="{ name: 'hi-developer' }">
                        {{ copy('forDevelopers') }} <ArrowRight :size="15" />
                    </AppButton>
                </div>
            </div>
        </header>

        <section class="hero-shell u-full">
            <div class="hero-visual" aria-hidden="true">
                <img class="hero-photo" :src="'/images/header-hero.png'" alt="">
                <div class="hero-photo-shade"></div>
            </div>

            <!-- No links, no rail. The rail is a decorative frame around the
                 links; with nothing in it the label and the line read as a
                 stray mark down the side of the page. -->
            <aside v-if="railLinks.length" class="social-rail" :aria-label="copy('socialFollow')">
                <span class="rail-role">{{ copy('railCta') }}</span>
                <div class="rail-line"></div>
                <a v-for="link in railLinks" :key="link.url" :href="link.url" :aria-label="link.label" rel="noopener">
                    <component :is="Icon(link.icon)" :size="20" aria-hidden="true" />
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

                    <div class="hero-stats">
                        <div v-for="metric in metrics" :key="t(metric.label)" class="metric">
                            <strong>{{ metric.value }}</strong>
                            <span>{{ t(metric.label) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="expertise" class="page-section expertise-section">
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

        <section id="about" class="page-section">
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

        <section id="work" class="page-section">
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

        <section id="contact" class="page-section contact-section">
            <div class="quote-card">
                <span>“</span>
                <p>{{ t(profile.quote) || copy('quote') }}</p>
                <strong>{{ t(profile.quote_author) }}</strong>
            </div>
            <div class="contact-band">
                <div class="contact-copy">
                    <h2 class="contact-headline">
                        <span v-for="line in copy('contactHeadlineLines')" :key="line">{{ line }}</span>
                    </h2>
                    <AppButton variant="menu-gold" class="contact-cta-link" as="a" href="mailto:hello@example.com">{{ copy('getInTouch') }} <ArrowRight :size="22" /></AppButton>
                </div>
            </div>

        </section>

        <!-- Three parts, so the links sit in the true centre of the page
             rather than wherever two notes of unequal length happen to leave
             them. The rail is desktop-only and sits beside the hero, so on a
             phone this is the only place these links appear at all. -->
        <footer class="site-footer">
            <span class="site-footer-note">{{ t(profile.location_note) }}</span>

            <nav v-if="footerLinks.length" class="social-footer" :aria-label="copy('socialFollow')">
                <a
                    v-for="link in footerLinks"
                    :key="link.url"
                    :href="link.url"
                    :aria-label="link.label"
                    :title="link.label"
                    rel="noopener"
                >
                    <component :is="Icon(link.icon)" :size="18" aria-hidden="true" />
                </a>
            </nav>
            <!-- Holds the middle track open when there are no links, so the
                 two notes stay pinned to the edges rather than one of them
                 sliding into the centre. Not needed once stacked. -->
            <span v-else class="site-footer-spacer" aria-hidden="true"></span>

            <span class="site-footer-note">{{ t(profile.availability_note) }}</span>
        </footer>

        <DeveloperConnectModal v-if="showConnectModal" />
    </main>
</template>
