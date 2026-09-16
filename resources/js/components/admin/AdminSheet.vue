<script setup>
import { computed, ref, useId, useSlots } from 'vue';

// The content card every workspace section sits in: page heading, the
// underline tab strip, the body, and the action bar pinned along the bottom.
// The only place this shape is rendered — Agenda's day/week/month switch and
// the Edit page's content tabs were two different controls doing one job.
//
// Passing `:tabs="[]"` still yields the card and its heading, just with no tab
// row, so a section without sub-views reads as the same shape as one with.
const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    // Sits under the title and says what this tab edits. Optional: a section
    // whose title already says it has nothing to add here.
    subtitle: {
        type: String,
        default: '',
    },
    tabs: {
        type: Array,
        default: () => [],
    },
    modelValue: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue']);

const slots = useSlots();

// The bar is chrome for whatever the page can do here. A section with nothing
// to say and nothing to do gets no empty 52px strip.
const hasBar = computed(() => Boolean(slots.status || slots.actions));

// Ties each tab to the panel it controls. useId keeps them unique when two
// sheets ever exist in one document.
const uid = useId();
const panelId = `${uid}-panel`;
const tabId = (value) => `${uid}-tab-${value}`;

const tabRefs = ref([]);

// Real tabs, so real tab keyboard handling: Left/Right move and select, and
// only the active tab is in the Tab order (roving tabindex). Without this a
// role="tab" is a promise the widget does not keep.
const moveFocus = (index) => {
    const item = props.tabs[index];

    if (!item) {
        return;
    }

    emit('update:modelValue', item.value);
    tabRefs.value[index]?.focus();
};

const onKeydown = (event, index) => {
    const last = props.tabs.length - 1;

    const target = {
        ArrowRight: index >= last ? 0 : index + 1,
        ArrowLeft: index <= 0 ? last : index - 1,
        Home: 0,
        End: last,
    }[event.key];

    if (target === undefined) {
        return;
    }

    event.preventDefault();
    moveFocus(target);
};
</script>

<template>
    <section class="admin-sheet">
        <header class="admin-sheet-head">
            <div class="admin-sheet-heading">
                <h1 class="admin-sheet-title">{{ title }}</h1>
                <p v-if="subtitle" class="admin-sheet-subtitle">{{ subtitle }}</p>
            </div>

            <div v-if="tabs.length > 1" class="admin-sheet-tabs" role="tablist" :aria-label="title">
                <button
                    v-for="(item, index) in tabs"
                    :id="tabId(item.value)"
                    :key="item.value"
                    ref="tabRefs"
                    type="button"
                    role="tab"
                    class="sheet-tab"
                    :class="{ active: modelValue === item.value, 'sheet-tab-right': item.right }"
                    :aria-selected="modelValue === item.value"
                    :aria-controls="panelId"
                    :tabindex="modelValue === item.value ? 0 : -1"
                    @click="$emit('update:modelValue', item.value)"
                    @keydown="onKeydown($event, index)"
                >
                    {{ item.label }}
                </button>
            </div>
        </header>

        <div
            :id="panelId"
            class="admin-sheet-body"
            :role="tabs.length > 1 ? 'tabpanel' : undefined"
            :aria-labelledby="tabs.length > 1 && modelValue ? tabId(modelValue) : undefined"
            tabindex="0"
        >
            <slot />
        </div>

        <!-- Sticky rather than merely last: on a long editor the save button
             has to stay reachable, which is the job the floating button it
             replaced was doing. -->
        <div v-if="hasBar" class="admin-sheet-bar">
            <p v-if="slots.status" class="admin-sheet-status">
                <slot name="status" />
            </p>

            <div class="admin-sheet-actions">
                <slot name="actions" />
            </div>
        </div>
    </section>
</template>
