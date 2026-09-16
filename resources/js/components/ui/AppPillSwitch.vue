<script setup>
// The one switcher shape in the workspace: a rounded track with a white chip
// on the selected option. EN/NL and light/dark in the rail, default language
// and show/hide in Settings, on/off for two-step sign-in — all the same
// control, so they cannot drift apart into five lookalikes.
//
// Colours come from --pill-* custom properties rather than a variant prop, so
// the rail (dark blue in dark mode) and the sheet (grey) each set them once on
// their own container and every pill inside inherits. Defaults below cover a
// pill used anywhere else.
defineProps({
    options: {
        type: Array,
        required: true,
    },
    modelValue: {
        type: [String, Boolean, Number],
        default: null,
    },
    // Names the set for a screen reader: "Theme", "Language". Required
    // because an icon-only pill has no visible label of its own.
    ariaLabel: {
        type: String,
        required: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <!-- radiogroup, not a row of toggle buttons: picking one deselects the
         other, and this way a reader announces "2 of 2" rather than leaving
         the relationship between the halves to be inferred. -->
    <span class="pill-switch" role="radiogroup" :aria-label="ariaLabel">
        <button
            v-for="option in options"
            :key="String(option.value)"
            type="button"
            role="radio"
            class="pill-switch-option"
            :class="{ active: option.value === modelValue, 'pill-switch-option-icon': !option.label }"
            :aria-checked="option.value === modelValue"
            :aria-label="option.label ? undefined : option.ariaLabel"
            :title="option.label ? undefined : option.ariaLabel"
            :disabled="disabled"
            @click="$emit('update:modelValue', option.value)"
        >
            <component :is="option.icon" v-if="option.icon" :size="14" aria-hidden="true" />
            <template v-if="option.label">{{ option.label }}</template>
        </button>
    </span>
</template>
