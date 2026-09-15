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
 * Where a social link shows. The rail and the footer are independent.
 *
 * Links saved before the split carry only is_visible, so it stands in for a
 * missing placement — otherwise both places would empty on every install that
 * already had links. Mirrored by showsIn() in PortfolioContentService.
 */
export const showsIn = (link, place) => {
    const explicit = link?.[place === 'rail' ? 'in_rail' : 'in_footer'];

    return explicit ?? (link?.is_visible !== false);
};

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
    ['metrics', 'expertise_items', 'projects', 'process_steps'].forEach((collection) => {
        const profileItems = payload.profile?.[collection];

        if ((!Array.isArray(payload[collection]) || payload[collection].length === 0) && Array.isArray(profileItems) && profileItems.length > 0) {
            payload[collection] = profileItems;
        }

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
    // The public endpoint drops links shown nowhere but still sends both
    // placements, and the admin reads the unfiltered payload through here too.
    const railLinks = computed(() => linksFor(profile.value.social_links, 'rail'));
    const footerLinks = computed(() => linksFor(profile.value.social_links, 'footer'));

    return {
        data,
        loading,
        fetchPortfolio,
        profile,
        metrics,
        expertise,
        projects,
        processSteps,
        railLinks,
        footerLinks,
    };
}
