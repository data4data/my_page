import { computed, ref } from 'vue';
import { apiFetch } from './api';

export const translatableProfile = [
    'role',
    'headline',
    'summary',
    'primary_cta_label',
    'secondary_cta_label',
    'location_note',
    'availability_note',
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
 * Where a social link shows. The rail and the footer are independent, so each
 * place draws its own set and each disappears on its own when nothing wants
 * it.
 *
 * A missing placement reads as shown. It used to stand in for the `is_visible`
 * switch the two placements replaced, back when links were a JSON column on
 * the profile; they are rows now, with real defaults, so this is only a guard
 * against a half-built object in an editor.
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
//
// It used to also read the collections back off payload.profile: the endpoint
// handed out the profile model with its relations loaded, so every row arrived
// twice, once nested and once at the top level. The payload is built by
// PortfolioProfileResource now and carries the profile's own fields only, so
// there is one place to read each collection from.
export function normalizePortfolio(payload) {
    ['social_links', 'metrics', 'expertise_items', 'projects', 'process_steps'].forEach((collection) => {
        if (!Array.isArray(payload[collection])) {
            payload[collection] = [];
        }
    });

    translatableProfile.forEach((field) => {
        payload.profile[field] = asTranslation(payload.profile[field]);
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

// Shared fetch + reactive slices for a portfolio payload (public read-only
// endpoint or the unfiltered admin endpoint — caller picks which).
export function usePortfolioSource(endpoint) {
    const data = ref(null);
    const loading = ref(true);

    const fetchPortfolio = async () => {
        loading.value = true;

        try {
            data.value = normalizePortfolio(await apiFetch(endpoint, { message: 'Could not load the page content.' }));
        } finally {
            // finally: a failed load must still clear the flag.
            loading.value = false;
        }
    };

    const profile = computed(() => data.value?.profile ?? {});
    const metrics = computed(() => data.value?.metrics ?? []);
    const expertise = computed(() => data.value?.expertise_items ?? []);
    const projects = computed(() => data.value?.projects ?? []);
    const processSteps = computed(() => data.value?.process_steps ?? []);
    // The public endpoint drops links shown in neither place — is_visible on
    // the row is generated from the two — and the admin reads the unfiltered
    // payload through here too.
    // A child collection like the four above, not a field on the profile.
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
