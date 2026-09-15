<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { X } from '@lucide/vue';
import { copy } from '../../shared/i18n';

/**
 * The overlay, card, close button and keyboard behaviour every modal needs.
 *
 * ConfirmDialog stays separate: it is an alertdialog raised from inside these,
 * so it sits on its own higher stacking layer.
 */
const props = defineProps({
    // Task editor: more fields than the connect form, so it gets more room.
    size: {
        type: String,
        default: 'default', // default | wide
    },
    // Names the dialog for screen readers.
    label: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close']);

const dialog = ref(null);

// Handed back on close, so focus does not jump to the top of the page.
let previouslyFocused = null;

// No offsetParent check: these modals use v-if, so anything hidden is not in
// the DOM, and it would make the trap depend on rendered geometry.
const focusableWithin = () => [...(dialog.value?.querySelectorAll(
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
) ?? [])].filter((element) => !element.closest('[hidden]'));

const onKeydown = (event) => {
    // A dropdown inside the modal handles its own Escape first.
    if (event.defaultPrevented) {
        return;
    }

    if (event.key === 'Escape') {
        emit('close');

        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    // Select and DatePicker overlays append to <body>, so focus can sit
    // outside this element. Only wrap when it is inside.
    if (!dialog.value?.contains(document.activeElement)) {
        return;
    }

    const focusable = focusableWithin();

    if (focusable.length === 0) {
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};

onMounted(async () => {
    previouslyFocused = document.activeElement;
    document.addEventListener('keydown', onKeydown);

    await nextTick();
    // The card, not the first field: an input would skip the heading.
    dialog.value?.focus();
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    previouslyFocused?.focus?.();
});
</script>

<template>
    <div class="connect-overlay" @click.self="emit('close')">
        <div
            ref="dialog"
            class="connect-modal"
            :class="{ 'connect-modal-wide': props.size === 'wide' }"
            role="dialog"
            aria-modal="true"
            :aria-label="props.label || undefined"
            tabindex="-1"
        >
            <button type="button" class="connect-close" :aria-label="copy('connectClose')" @click="emit('close')">
                <X :size="18" />
            </button>

            <slot />
        </div>
    </div>
</template>
