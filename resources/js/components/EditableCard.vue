<script setup>
import { ChevronDown, ChevronUp, Trash2 } from '@lucide/vue';
import AppCheckbox from './ui/AppCheckbox.vue';
import { copy } from '../shared/i18n';

// One item in an ordered, editable collection: the head carries the item's
// name, position, visibility and reorder controls, the slot holds its fields.
defineProps({
    title: {
        type: String,
        required: true,
    },
    index: {
        type: Number,
        required: true,
    },
    // Shown as "2 of 5": a position with no total is just a number.
    total: {
        type: Number,
        required: true,
    },
    collection: {
        type: String,
        required: true,
    },
    // Optional: social links derive visibility from their two placements.
    visible: {
        type: Boolean,
        default: null,
    },
});

defineEmits(['move', 'remove', 'update:visible']);
</script>

<template>
    <section class="item-card">
        <header class="item-card-head">
            <slot name="lead" />

            <strong class="item-card-title">{{ title }}</strong>
            <span class="item-card-position">{{ index + 1 }} {{ copy('positionOf') }} {{ total }}</span>

            <div class="item-card-tools">
                <AppCheckbox
                    v-if="visible !== null"
                    :model-value="visible"
                    @update:model-value="$emit('update:visible', $event)"
                >{{ copy('visible') }}</AppCheckbox>

                <span v-if="visible !== null" class="item-card-divider" aria-hidden="true"></span>

                <button
                    type="button"
                    class="icon-button"
                    :disabled="index === 0"
                    :aria-label="copy('moveUp')"
                    :title="copy('moveUp')"
                    @click="$emit('move', collection, index, -1)"
                >
                    <ChevronUp :size="14" aria-hidden="true" />
                </button>

                <button
                    type="button"
                    class="icon-button"
                    :disabled="index === total - 1"
                    :aria-label="copy('moveDown')"
                    :title="copy('moveDown')"
                    @click="$emit('move', collection, index, 1)"
                >
                    <ChevronDown :size="14" aria-hidden="true" />
                </button>

                <button
                    type="button"
                    class="icon-button icon-button-danger"
                    :aria-label="copy('remove')"
                    :title="copy('remove')"
                    @click="$emit('remove', collection, index)"
                >
                    <Trash2 :size="14" aria-hidden="true" />
                </button>
            </div>
        </header>

        <div class="item-card-body">
            <slot />
        </div>
    </section>
</template>
