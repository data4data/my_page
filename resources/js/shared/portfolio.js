import { computed, ref } from 'vue';

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

export const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const asTranslation = (value) => {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        return { en: value.en ?? '', nl: value.nl ?? value.en ?? '' };
    }

    return { en: value ?? '', nl: value ?? '' };
};

// Fills in missing collections/translation shapes so every template can rely
// on data.profile.<field>.en/.nl and data.<collection> always being arrays.
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

            if (collection === 'expertise_items') {
                item.gear_size = Number(item.gear_size) || 120;
            }
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
        const response = await fetch(endpoint);
        data.value = normalizePortfolio(await response.json());
        loading.value = false;
    };

    const profile = computed(() => data.value?.profile ?? {});
    const metrics = computed(() => data.value?.metrics ?? []);
    const expertise = computed(() => data.value?.expertise_items ?? []);
    const projects = computed(() => data.value?.projects ?? []);
    const processSteps = computed(() => data.value?.process_steps ?? []);
    const socialLinks = computed(() => (Array.isArray(profile.value.social_links) ? profile.value.social_links : []));

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
    };
}
