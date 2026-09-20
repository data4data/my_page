<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { X } from '@lucide/vue';
import AppButton from './AppButton.vue';
import { copy } from '../../shared/i18n';
import { useConfirm } from '../../shared/confirm';

const { pending, respond } = useConfirm();

const dialog = ref(null);

// On document, not the overlay: the overlay never takes focus, so a key event
// reaches it only after a click inside, leaving the promise pending otherwise.
const onKeydown = (event) => {
    if (event.key === 'Escape' && pending.value) {
        respond(false);
    }
};

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));

// Focus moves into the dialog, which otherwise stays on the button that raised
// the question, now behind an inert overlay.
watch(pending, async (value) => {
    if (!value) {
        return;
    }

    await nextTick();
    dialog.value?.focus();
});
</script>

<template>
    <!-- Dismissing any other way counts as cancel, so no caller is left
         hanging. layer-confirm, since a confirm is usually raised from inside
         another modal and has to sit above it. -->
    <div v-if="pending" class="modal-overlay admin-modal-overlay layer-confirm" @click.self="respond(false)">
        <div ref="dialog" class="admin-modal admin-modal-narrow" role="alertdialog" aria-modal="true" tabindex="-1">
            <header class="admin-modal-head">
                <div class="admin-modal-heading">
                    <h2 class="admin-modal-title">{{ copy('confirmTitle') }}</h2>
                </div>

                <button type="button" class="admin-modal-close" :aria-label="copy('confirmCancel')" @click="respond(false)">
                    <X :size="18" />
                </button>
            </header>

            <div class="admin-modal-body">
                <p class="text-sm leading-6 text-body">{{ pending.message }}</p>
            </div>

            <footer class="admin-modal-bar">
                <div class="admin-modal-actions">
                    <AppButton variant="outline" @click="respond(false)">
                        {{ pending.cancelLabel ?? copy('confirmCancel') }}
                    </AppButton>
                    <AppButton variant="solid" @click="respond(true)">
                        {{ pending.confirmLabel ?? copy('confirmAccept') }}
                    </AppButton>
                </div>
            </footer>
        </div>
    </div>
</template>