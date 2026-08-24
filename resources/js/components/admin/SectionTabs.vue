<script setup>
// Shared "folder bookmark" tab strip + content card, used by every admin
// section that has its own in-page sub-views (Edit page's content tabs,
// Agenda's day/week/month/categories/report modes). Passing an empty/
// single-item `tabs` array (e.g. Insights) still yields the matching
// bordered `.admin-panel.rounded-t-none` card, just without a visible tab
// row — so every section reads as the same shape of card regardless of
// how many sub-views it actually has.
defineProps({
    tabs: {
        type: Array,
        default: () => [],
    },
    modelValue: {
        type: String,
        default: null,
    },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <nav v-if="tabs.length > 1" class="flex flex-wrap gap-1 pl-5 md:pl-7">
        <button
            v-for="item in tabs"
            :key="item.value"
            type="button"
            class="bookmark-tab"
            :class="{ active: modelValue === item.value, 'ml-auto': item.right }"
            @click="$emit('update:modelValue', item.value)"
        >
            {{ item.label }}
        </button>
    </nav>

    <div class="admin-panel rounded-t-none">
        <slot />
    </div>
</template>
