<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { X } from '@lucide/vue';
import AppButton from './AppButton.vue';
import { copy } from '../../shared/i18n';
import { useConfirm } from '../../shared/confirm';

const { pending, respond } = useConfirm();

const dialog = ref(null);

// Escape has to be listened for on document, not on the overlay: the overlay
// is a plain <div> that never receives focus, so a key event only reaches it
// if the user has already clicked inside — leaving the awaiting confirm()
// promise pending forever in every other case.
const onKeydown = (event) => {
    if (event.key === 'Escape' && pending.value) {
        respond(false);
    }
};

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));

// Move focus into the dialog when it opens so screen readers announce it and
// Tab stays in a sensible place; without this, focus is left on whatever
// button raised the question, which is now behind an inert overlay.
watch(pending, async (value) => {
    if (!value) {
        return;
    }

    await nextTick();
    dialog.value?.focus();
});
</script>

<template>
    <!-- Dismissing any other way (overlay click, close, Escape) counts as
         cancel, so the awaiting caller is never left hanging. -->
    <!-- layer-confirm: a confirm is usually raised *from* another modal, so it
         has to sit above it. See the --z-* scale in resources/css/base.css. -->
    <div v-if="pending" class="connect-overlay layer-confirm" @click.self="respond(false)">
        <div ref="dialog" class="connect-modal connect-modal-narrow" role="alertdialog" aria-modal="true" tabindex="-1">
            <button type="button" class="connect-close" :aria-label="copy('confirmCancel')" @click="respond(false)">
                <X :size="18" />
            </button>

            <p class="eyebrow">{{ copy('confirmTitle') }}</p>
            <p class="mt-3 text-sm leading-6 text-ink">{{ pending.message }}</p>

            <div class="mt-6 flex items-center justify-end gap-2">
                <AppButton variant="secondary" size="sm" @click="respond(false)">
                    {{ pending.cancelLabel ?? copy('confirmCancel') }}
                </AppButton>
                <AppButton variant="primary" size="sm" @click="respond(true)">
                    {{ pending.confirmLabel ?? copy('confirmAccept') }}
                </AppButton>
            </div>
        </div>
    </div>
</template>
