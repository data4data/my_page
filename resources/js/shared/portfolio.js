import { computed, ref } from 'vue';
import { apiFetch } from './api';

export const translatableProfile = [
    'role',
    'headline',
    'summary',
    'primary_cta_label',
    'secondary_cta_label',
    'footer_note_left',
    'footer_note_right',
    'quote',
    'quote_author',
];

export const translatableItemFields = {
    metrics: ['label'],
    expertise_items: ['title', 'description'],
    projects: ['title', 'summary', 'result'],
    process_steps: ['title', 'description'],
};

/**
 * The decorative panel beside a project card, mirroring App\Enums\VisualStyle
 * — each value is a class on `.project-visual` in `public.css`, so one that is
 * not on this list draws a blank panel. `portfolio-fields.test.js` reads the
 * PHP enum and fails when the two drift.
 */
export const VISUAL_STYLES = ['dashboard', 'flow', 'cms'];

/**
 * Where a social link shows. The rail and the footer are independent, so each
 * place draws its own set. A missing placement reads as shown, which only
 * guards against a half-built object in the editor.
 */
export const showsIn = (link, place) => link?.[place === 'rail' ? 'in_rail' : 'in_footer'] ?? true;

export const linksFor = (links, place) => (Array.isArray(links) ? links : [])
    .filter((link) => link?.url && showsIn(link, place));

const asTranslation = (value) => {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        return { en: value.en ?? '', nl: value.nl ?? value.en ?? '' };
    }

    return { en: value ?? '', nl: value ?? '' };
};

// Fills in missing collections and translation shapes, so templates can rely
// on .en/.nl and on the collections always being arrays.
export function normalizePortfolio(payload) {
    ['social_links', 'metrics', 'expertise_items', 'projects', 'process_steps'].forEach((collection) => {
        if (!Array.isArray(payload[collection])) {
            payload[collection] = [];
        }
    });

    translatableProfile.forEach((field) => {
        payload.profile[field] = asTranslation(payload.profile[field]);
    });

    // A style with no class behind it would draw a blank panel, so anything
    // unrecognised — a value typed in before this was a list — reads as the
    // first one. The editor then saves a style the rules accept.
    payload.projects?.forEach((project) => {
        if (!VISUAL_STYLES.includes(project.visual_style)) {
            project.visual_style = VISUAL_STYLES[0];
        }
    });

    Object.entries(translatableItemFields).forEach(([collection, fields]) => {
        payload[collection]?.forEach((item) => {
            fields.forEach((field) => {
                item[field] = asTranslation(item[field]);
            });
        });
    });

    return payload;
}

// Fetch + reactive slices for a portfolio payload; the caller picks the
// public endpoint or the unfiltered admin one.
export function usePortfolioSource(endpoint) {
    const data = ref(null);
    const loading = ref(true);

    const fetchPortfolio = async () => {
        loading.value = true;

        try {
            data.value = normalizePortfolio(await apiFetch(endpoint));
        } finally {
            // A failed load must still clear the flag.
            loading.value = false;
        }
    };

    const profile = computed(() => data.value?.profile ?? {});
    const metrics = computed(() => data.value?.metrics ?? []);
    const expertise = computed(() => data.value?.expertise_items ?? []);
    const projects = computed(() => data.value?.projects ?? []);
    const processSteps = computed(() => data.value?.process_steps ?? []);
    const socialLinks = computed(() => data.value?.social_links ?? []);
    const railLinks = computed(() => linksFor(socialLinks.value, 'rail'));
    const footerLinks = computed(() => linksFor(socialLinks.value, 'footer'));

    return {
        data,
        loading,
        fetchPortfolio,
        profile,
        metrics,
        expertise,
        projects,
        processSteps,
        socialLinks,
        railLinks,
        footerLinks,
    };
}
