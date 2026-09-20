<script setup>
import { computed } from 'vue';
import { LANGUAGES } from '../../shared/i18n';

// Two cards side by side, one per language. Replaces the per-field EN/NL pair,
// which interleaved the two so reading one language meant reading every other
// row. The slot is rendered once per language, so a tab declares fields once.
const props = defineProps({
    // Which language leads: the one a first-time visitor sees.
    defaultLanguage: {
        type: String,
        default: 'en',
    },
});

const cards = computed(() => {
    const known = LANGUAGES.map((language) => language.value);
    const lead = known.includes(props.defaultLanguage) ? props.defaultLanguage : known[0];

    // The column order is the reading order, so it follows the setting.
    return [...LANGUAGES].sort((a, b) => Number(b.value === lead) - Number(a.value === lead));
});
</script>

<template>
    <div class="lang-grid">
        <section v-for="language in cards" :key="language.value" class="lang-card">
            <header class="lang-card-head">
                <span
                    class="lang-card-tag"
                    :class="{ 'lang-card-tag-default': language.value === defaultLanguage }"
                >{{ language.value.toUpperCase() }}</span>
                <span class="lang-card-name">{{ language.label }}</span>
            </header>

            <div class="lang-card-body">
                <slot :locale="language.value" :is-default="language.value === defaultLanguage" />
            </div>
        </section>
    </div>
</template>
