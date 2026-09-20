<script setup>
// The one switcher shape in the workspace, so EN/NL, light/dark, default
// language and show/hide cannot drift into lookalikes. Colours come from
// --pill-* set by whatever contains it, not from a variant prop.
defineProps({
    options: {
        type: Array,
        required: true,
    },
    modelValue: {
        type: [String, Boolean, Number],
        default: null,
    },
    // Required: an icon-only pill has no visible label of its own.
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
    <!-- radiogroup, not toggle buttons: picking one deselects the other, and a
         reader announces the relationship rather than leaving it inferred. -->
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
