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
        default: 'primary', // primary | secondary | menu | lang | link | icon
    },
    as: {
        type: String,
        default: 'button',
    },
    active: {
        type: Boolean,
        default: false,
    },
});

const variantClass = {
    primary: 'dark-button',
    secondary: 'light-button',
    menu: 'menu-button',
    lang: 'lang-button',
    link: 'text-link',
    icon: 'icon-button',
};

const rootClass = computed(() => [
    variantClass[props.variant] ?? variantClass.primary,
    { active: props.active },
]);
</script>

<template>
    <Button :as="as" :class="rootClass" unstyled>
        <slot />
    </Button>
</template>
