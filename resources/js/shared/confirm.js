import { ref } from 'vue';

// A module-level singleton, like toast.js: any component can ask, and the one
// mounted <ConfirmDialog /> renders it.
const pending = ref(null);

// Resolves true when confirmed, false when cancelled or dismissed.
const ask = (options) => new Promise((resolve) => {
    // A second question would strand the first promise.
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
