<script setup>
import { computed } from 'vue';
import { LANGUAGES } from '../../shared/i18n';

// Two cards side by side, one per language, each holding that language's half
// of every translated field on an item.
//
// Replaces the per-field EN/NL pair: a card with four translated fields used
// to interleave them (EN title, NL title, EN description, NL description), so
// reading one language's copy end to end meant reading every other row. The
// slot is rendered once per language with that language's key, so a tab still
// declares each field exactly once.
const props = defineProps({
    // Which language leads. Its card is tagged as the default, because that
    // is the one a first-time visitor sees.
    defaultLanguage: {
        type: String,
        default: 'en',
    },
});

const cards = computed(() => {
    const known = LANGUAGES.map((language) => language.value);
    const lead = known.includes(props.defaultLanguage) ? props.defaultLanguage : known[0];

    // The default first, whichever it is — the column order is the reading
    // order, so it has to follow the setting rather than the array literal.
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
