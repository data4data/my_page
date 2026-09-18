import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * The keyboard and focus behaviour every modal needs.
 *
 * There are two shells — AdminModal and PublicModal — because the workspace
 * and the visit card are two different palettes and two different card
 * shapes. They must not be two different *dialogs*, though: Escape, the Tab
 * trap and handing focus back are the parts a user notices only when they are
 * missing, and behaviour copied into two files is behaviour that drifts.
 *
 * Call it with the function that closes the modal; bind the returned ref to
 * the element carrying role="dialog".
 */
export const useModalDialog = (close) => {
    const dialog = ref(null);

    // Handed back on close, so focus does not jump to the top of the page.
    let previouslyFocused = null;

    // No offsetParent check: these modals use v-if, so anything hidden is not
    // in the DOM, and it would make the trap depend on rendered geometry.
    const focusableWithin = () => [...(dialog.value?.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ) ?? [])].filter((element) => !element.closest('[hidden]'));

    const onKeydown = (event) => {
        // A dropdown inside the modal handles its own Escape first.
        if (event.defaultPrevented) {
            return;
        }

        if (event.key === 'Escape') {
            close();

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

    return { dialog };
};
