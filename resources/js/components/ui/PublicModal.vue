<script setup>
import { X } from '@lucide/vue';
import { copy } from '../../shared/i18n';
import { useModalDialog } from '../../shared/modal';

/**
 * The visit card's modal: the brand palette, one scrolling body, and the
 * heading left to the caller — the connect form swaps its whole head between
 * the form and the thank-you.
 *
 * Deliberately light-only. The public page has no dark mode (see the theme
 * block in resources/css/theme.css), and this card is the one place a
 * workspace-themed field could leak onto it, which is why .public-modal maps
 * the surface tokens forms.css reads back onto the brand palette.
 *
 * AdminModal is its counterpart in the workspace; the behaviour both need
 * lives in shared/modal.js.
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
