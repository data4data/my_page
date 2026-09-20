<script setup>
import { X } from '@lucide/vue';
import { copy } from '../../shared/i18n';
import { useModalDialog } from '../../shared/modal';

/**
 * The visit card's modal: the brand palette, one scrolling body, and the
 * heading left to the caller, since the connect form swaps its whole head for
 * the thank-you. AdminModal is its counterpart; shared/modal.js holds the
 * behaviour both need.
 */
const props = defineProps({
    // Names the dialog for screen readers.
    label: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close']);

const { dialog } = useModalDialog(() => emit('close'));
</script>

<template>
    <div class="modal-overlay public-modal-overlay" @click.self="emit('close')">
        <div
            ref="dialog"
            class="public-modal"
            role="dialog"
            aria-modal="true"
            :aria-label="props.label || undefined"
            tabindex="-1"
        >
            <button type="button" class="public-modal-close" :aria-label="copy('connectClose')" @click="emit('close')">
                <X :size="18" />
            </button>

            <div class="public-modal-body">
                <slot />
            </div>
        </div>
    </div>
</template>
