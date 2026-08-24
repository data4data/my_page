import { ref } from 'vue';

// Module-level singleton (same pattern as `lang` in i18n.js): one shared
// stack, so any component can push a toast and <ToastStack /> renders
// whatever's currently queued, wherever it happens to be mounted.
const toasts = ref([]);
let nextId = 1;

const AUTO_DISMISS_MS = {
    success: 4000,
    warning: 5500,
    error: 6500,
};

const push = (type, text) => {
    const id = nextId++;
    toasts.value.push({ id, type, text });
    setTimeout(() => dismiss(id), AUTO_DISMISS_MS[type]);
    return id;
};

const dismiss = (id) => {
    const index = toasts.value.findIndex((toast) => toast.id === id);
    if (index !== -1) {
        toasts.value.splice(index, 1);
    }
};

export const useToast = () => ({
    toasts,
    dismiss,
    success: (text) => push('success', text),
    error: (text) => push('error', text),
    warning: (text) => push('warning', text),
});
