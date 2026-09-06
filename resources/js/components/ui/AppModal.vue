<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { X } from '@lucide/vue';
import { copy } from '../../shared/i18n';

/**
 * The overlay, the card, the close button and the keyboard behaviour every
 * modal in this app needs. Three components had the first three copied by
 * hand and none of the fourth: no role, no focus handling, and no way to
 * leave with the keyboard.
 *
 * ConfirmDialog stays separate on purpose — it is an alertdialog raised from
 * *inside* these, so it carries its own higher stacking layer.
 */
const props = defineProps({
    // Task editor: more fields than the connect form, so it gets more room.
    size: {
        type: String,
        default: 'default', // default | wide
    },
    // Names the dialog for screen readers, which otherwise announce it as an
    // unlabelled group.
    label: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close']);

const dialog = ref(null);

// What had focus before the modal opened, so it can be handed back on close
// rather than dumping the user at the top of the page.
let previouslyFocused = null;

// No layout-based visibility check: these modals show and hide with v-if, so
// anything hidden is not in the DOM to begin with, and offsetParent would
// make the trap depend on rendered geometry.
const focusableWithin = () => [...(dialog.value?.querySelectorAll(
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
) ?? [])].filter((element) => !element.closest('[hidden]'));

const onKeydown = (event) => {
    // A PrimeVue dropdown inside the modal handles its own Escape; closing
    // the whole modal out from under it would be the wrong response.
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

    // Select and DatePicker overlays are appended to <body>, so focus can
    // legitimately sit outside this element. Only wrap when it is inside,
    // rather than yanking it back out of a portal.
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
    // The card itself, not the first field: landing on an input skips the
    // heading a screen reader should read first.
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
