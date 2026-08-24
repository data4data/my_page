<script setup>
import { computed } from 'vue';
import Button from 'primevue/button';

/**
 * Single place that maps a button "variant" to how it looks across the whole app.
 * Change the classes below (or in resources/css/app.css) once, instead of hunting
 * for every hardcoded <button>/<a> in the templates.
 */
const props = defineProps({
    variant: {
        type: String,
        default: 'primary', // primary | secondary | accent | menu | menu-gold | lang | link | icon | icon-danger
    },
    as: {
        type: String,
        default: 'button',
    },
    active: {
        type: Boolean,
        default: false,
    },
    size: {
        type: String,
        default: 'md', // md | sm — 'sm' trims padding/shadow for tight bars (e.g. the admin header)
    },
});

const variantClass = {
    primary: 'dark-button',
    secondary: 'light-button',
    accent: 'accent-button',
    menu: 'menu-button',
    'menu-gold': 'menu-button menu-button-gold',
    lang: 'lang-button',
    link: 'text-link',
    icon: 'icon-button',
    'icon-danger': 'icon-button-danger',
};

const rootClass = computed(() => [
    variantClass[props.variant] ?? variantClass.primary,
    { active: props.active, 'btn-compact': props.size === 'sm' },
]);
</script>

<template>
    <Button :as="as" :class="rootClass" unstyled>
        <slot />
    </Button>
</template>
