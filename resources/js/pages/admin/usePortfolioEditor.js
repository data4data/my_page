import { computed, inject, provide, ref } from 'vue';
import { adminUrl } from '../../shared/admin-path';
import { apiFetch, reportError } from '../../shared/api';
import { copy } from '../../shared/i18n';
import { usePortfolioSource } from '../../shared/portfolio';
import { useToast } from '../../shared/toast';
import { useConfirm } from '../../shared/confirm';

const KEY = Symbol('portfolio-editor');

/**
 * The unsaved public-page payload, and everything that writes it.
 *
 * It is provided once by AdminPage rather than created per section, because
 * two sections edit it — Edit page, and Settings' Language tab — and an edit
 * made in one must survive walking over to the other. Everything else in the
 * workspace persists as you act on it and owns its own loading.
 */
export function providePortfolioEditor() {
    const toast = useToast();
    const { confirm } = useConfirm();

    const source = usePortfolioSource(adminUrl('/portfolio'));
    const { data, loading, fetchPortfolio } = source;

    const saving = ref(false);
    const restoring = ref(false);
    const restoringId = ref(null);
    const revisions = ref([]);
    const revisionsLoading = ref(true);

    // What the server last confirmed, serialised. Compared rather than
    // watched: a deep watcher also fires when fetchPortfolio() replaces the
    // payload, which would report edits nobody made.
    const savedPayload = ref('');
    const dirty = computed(() => Boolean(savedPayload.value) && savedPayload.value !== JSON.stringify(data.value));
    const saveStatus = computed(() => (dirty.value ? copy('unsavedChanges') : copy('allSaved')));

    /**
     * Nothing confirmed to save. Saving an empty editor would replace the live
     * page with nothing, and the editor looks the same either way — a page
     * with no content and a page that failed to arrive both render blank.
     */
    const ready = computed(() => Boolean(savedPayload.value));
    const loadFailed = computed(() => !loading.value && data.value === null);

    const load = () => fetchPortfolio().then(() => {
        savedPayload.value = JSON.stringify(data.value);
    });

    const reload = () => load().catch((error) => reportError(error, copy('contentLoadError')));

    const fetchRevisions = async () => {
        revisionsLoading.value = true;

        try {
            revisions.value = (await apiFetch(adminUrl('/portfolio/revisions'))).revisions ?? [];
        } finally {
            revisionsLoading.value = false;
        }
    };

    const loadRevisions = () => fetchRevisions().catch((error) => reportError(error, copy('historyLoadError')));

    // Save, reset and restore all end the same way: re-read what the server
    // now holds, and show the version the write just made.
    const afterWrite = async (message) => {
        await load();
        await fetchRevisions();
        toast.success(message);
    };

    const save = async () => {
        saving.value = true;

        try {
            await apiFetch(adminUrl('/portfolio'), { method: 'PUT', body: data.value });
            await afterWrite(copy('saved'));
        } catch (error) {
            reportError(error, copy('error'));
        } finally {
            saving.value = false;
        }
    };

    // Both restores ask first: they overwrite the live public page, and the
    // defaults sit one click from the saved versions.
    const restoreDefaults = async () => {
        if (!await confirm({ message: copy('restoreConfirm'), confirmLabel: copy('historyRestore') })) {
            return;
        }

        restoring.value = true;

        try {
            await apiFetch(adminUrl('/portfolio/seed-defaults'), { method: 'POST' });
            await afterWrite(copy('restored'));
        } catch (error) {
            reportError(error, copy('restoreError'));
        } finally {
            restoring.value = false;
        }
    };

    const restoreRevision = async (id) => {
        if (!await confirm({ message: copy('historyConfirm'), confirmLabel: copy('historyRestore') })) {
            return;
        }

        restoringId.value = id;

        try {
            await apiFetch(adminUrl(`/portfolio/revisions/${id}/restore`), { method: 'POST' });
            await afterWrite(copy('historyRestored'));
        } catch (error) {
            reportError(error, copy('restoreError'));
        } finally {
            restoringId.value = null;
        }
    };

    const addItem = (collection, item) => {
        data.value[collection].push({ ...item, is_visible: true });
    };

    const removeItem = (collection, index) => {
        data.value[collection].splice(index, 1);
    };

    const moveItem = (collection, index, direction) => {
        const items = data.value[collection];
        const next = index + direction;

        if (next < 0 || next >= items.length) {
            return;
        }

        [items[index], items[next]] = [items[next], items[index]];
    };

    const editor = {
        ...source,
        ready,
        loadFailed,
        dirty,
        saveStatus,
        saving,
        restoring,
        restoringId,
        revisions,
        revisionsLoading,
        load,
        reload,
        loadRevisions,
        save,
        restoreDefaults,
        restoreRevision,
        addItem,
        removeItem,
        moveItem,
    };

    provide(KEY, editor);

    return editor;
}

/** The editor AdminPage provided. */
export const usePortfolioEditor = () => inject(KEY);
