<script setup>
import { ChevronDown, ChevronUp, Trash2 } from '@lucide/vue';

defineProps({
    title: {
        type: String,
        required: true,
    },
    index: {
        type: Number,
        required: true,
    },
    collection: {
        type: String,
        required: true,
    },
});

defineEmits(['move', 'remove']);
</script>

<template>
    <section class="editable-card">
        <header>
            <strong>{{ title }} {{ index + 1 }}</strong>
            <div>
                <button type="button" aria-label="Move up" @click="$emit('move', collection, index, -1)">
                    <ChevronUp :size="16" />
                </button>
                <button type="button" aria-label="Move down" @click="$emit('move', collection, index, 1)">
                    <ChevronDown :size="16" />
                </button>
                <button type="button" aria-label="Remove" @click="$emit('remove', collection, index)">
                    <Trash2 :size="16" />
                </button>
            </div>
        </header>
        <div class="admin-grid">
            <slot />
        </div>
    </section>
</template>
