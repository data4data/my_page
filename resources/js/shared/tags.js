import { ref } from 'vue';
import { apiFetch } from './api';
import { adminUrl } from './admin-path';

/**
 * The vocabulary the Projects editor offers, as plain names.
 *
 * Module-level rather than per-component: every project card on the tab shows
 * the same list, and one fetch is enough for all of them. A tag added from one
 * card therefore appears in the next card's dropdown without a reload.
 */
const tags = ref([]);
const loaded = ref(false);

export function useTags() {
    const loadTags = async () => {
        const body = await apiFetch(adminUrl('/tags'));
        tags.value = body.tags ?? [];
        loaded.value = true;
    };

    // Kept in order, so a new tag lands where the list expects it rather than
    // at the bottom until the next reload.
    const createTag = async (name) => {
        const body = await apiFetch(adminUrl('/tags'), { method: 'POST', body: { name } });
        const created = body.tag;

        if (!tags.value.includes(created)) {
            tags.value = [...tags.value, created].sort((a, b) => a.localeCompare(b));
        }

        return created;
    };

    return { tags, loaded, loadTags, createTag };
}
