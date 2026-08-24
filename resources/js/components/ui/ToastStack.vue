<script setup>
import { CheckCircle2, AlertTriangle, XCircle, X } from '@lucide/vue';
import { useToast } from '../../shared/toast';

const { toasts, dismiss } = useToast();

const icons = {
    success: CheckCircle2,
    warning: AlertTriangle,
    error: XCircle,
};
</script>

<template>
    <div class="layer-toast pointer-events-none fixed right-5 top-20 flex w-full max-w-sm flex-col gap-3">
        <!-- layer-toast, not a raw z utility: toasts must outrank every
             overlay, since the errors they carry are often raised from inside
             a modal. Kept inside the root so this stays a single-root
             component. -->
        <TransitionGroup name="toast">
            <div v-for="toast in toasts" :key="toast.id" class="app-toast pointer-events-auto" :class="`app-toast-${toast.type}`">
                <component :is="icons[toast.type]" :size="18" class="mt-0.5 shrink-0" />
                <p class="text-sm leading-5">{{ toast.text }}</p>
                <button type="button" class="app-toast-close" aria-label="Dismiss" @click="dismiss(toast.id)">
                    <X :size="14" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
