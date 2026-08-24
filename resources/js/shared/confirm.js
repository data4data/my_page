import { ref } from 'vue';

// Module-level singleton (same pattern as toast.js): any component can ask a
// question and <ConfirmDialog /> — mounted once — renders it. Replaces
// window.confirm(), which can't be styled and looks like a browser warning
// rather than part of the app.
const pending = ref(null);

// Resolves true when confirmed, false when cancelled/dismissed, so callers
// read as: if (!await confirm({ ... })) return;
const ask = (options) => new Promise((resolve) => {
    // A second question while one is open would strand the first promise —
    // resolve it as cancelled before replacing it.
    pending.value?.resolve(false);

    pending.value = {
        message: options.message,
        confirmLabel: options.confirmLabel ?? null,
        cancelLabel: options.cancelLabel ?? null,
        resolve,
    };
});

const respond = (answer) => {
    pending.value?.resolve(answer);
    pending.value = null;
};

export const useConfirm = () => ({
    pending,
    confirm: ask,
    respond,
});
