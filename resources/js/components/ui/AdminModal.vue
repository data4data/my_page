<script setup>
import { X } from '@lucide/vue';
import { copy } from '../../shared/i18n';
import { useModalDialog } from '../../shared/modal';

/**
 * The workspace modal: head, scrolling body, action bar — the same three bands
 * AdminSheet has, drawn from the surface tokens so it follows light and dark.
 * PublicModal is its counterpart; shared/modal.js holds the shared behaviour.
 */
const props = defineProps({
    size: {
        type: String,
        default: 'default', // default | wide | narrow
    },
    eyebrow: {
        type: String,
        default: '',
    },
    title: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
    // Names the dialog for screen readers. Defaults to the visible title.
    label: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close']);

const { dialog } = useModalDialog(() => emit('close'));
</script>

<template>
    <div class="modal-overlay admin-modal-overlay" @click.self="emit('close')">
        <div
            ref="dialog"
            class="admin-modal"
            :class="{
                'admin-modal-wide': props.size === 'wide',
                'admin-modal-narrow': props.size === 'narrow',
            }"
            role="dialog"
            aria-modal="true"
            :aria-label="props.label || props.title || undefined"
            tabindex="-1"
        >
            <header class="admin-modal-head">
                <div class="admin-modal-heading">
                    <p v-if="props.eyebrow" class="admin-modal-eyebrow">{{ props.eyebrow }}</p>
                    <h2 v-if="props.title" class="admin-modal-title">{{ props.title }}</h2>
                    <p v-if="props.subtitle" class="admin-modal-subtitle">{{ props.subtitle }}</p>
                </div>

                <!-- Beside the title rather than in the body: the task timer. -->
                <slot name="head-aside" />

                <button type="button" class="admin-modal-close" :aria-label="copy('connectClose')" @click="emit('close')">
                    <X :size="18" />
                </button>
            </header>

            <div class="admin-modal-body">
                <slot />
            </div>

            <!-- No slot, no bar: a modal that only reads gets no empty strip. -->
            <footer v-if="$slots.footer" class="admin-modal-bar">
                <slot name="footer" />
            </footer>
        </div>
    </div>
</template>
