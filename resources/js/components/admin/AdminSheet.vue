<script setup>
import { computed, ref, useId, useSlots } from 'vue';

// The content card every workspace section sits in: heading, tab strip, body,
// and the action bar along the bottom. Passing `:tabs="[]"` still yields the
// card and its heading, just with no tab row.
const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    // Says what this tab edits; optional, when the title already does.
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

// A section with nothing to say and nothing to do gets no empty strip.
const hasBar = computed(() => Boolean(slots.status || slots.actions));

// Ties each tab to the panel it controls; useId keeps two sheets apart.
const uid = useId();
const panelId = `${uid}-panel`;
const tabId = (value) => `${uid}-tab-${value}`;

const tabRefs = ref([]);

// Real tab semantics need real keyboard handling: Left/Right move and select,
// and only the active tab is in the Tab order.
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

        <!-- Sticky, so the save button stays reachable down a long editor. -->
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
