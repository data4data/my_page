<script setup>
import AppInput from '../../components/ui/AppInput.vue';
import AppTextarea from '../../components/ui/AppTextarea.vue';
import AppLanguageCards from '../../components/ui/AppLanguageCards.vue';
import { copy } from '../../shared/i18n';

// Translated copy only; the untranslated values live on the Shared tab, so a
// column here reads as one language's page.
defineProps({
    profile: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <AppLanguageCards :default-language="profile.default_language">
        <template #default="{ locale }">
            <span class="field-section-heading">{{ copy('groupHero') }}</span>
            <label class="field-label">{{ copy('fieldRole') }}<AppInput v-model="profile.role[locale]" /></label>
            <label class="field-label">{{ copy('fieldHeadline') }}<AppTextarea v-model="profile.headline[locale]" rows="2" /></label>
            <label class="field-label">{{ copy('fieldSummary') }}<AppTextarea v-model="profile.summary[locale]" rows="3" /></label>

            <span class="field-section-heading">{{ copy('groupButtons') }}</span>
            <label class="field-label">{{ copy('fieldPrimaryCtaLabel') }}<AppInput v-model="profile.primary_cta_label[locale]" /></label>
            <label class="field-label">{{ copy('fieldSecondaryCtaLabel') }}<AppInput v-model="profile.secondary_cta_label[locale]" /></label>

            <span class="field-section-heading">{{ copy('groupFooter') }}</span>
            <label class="field-label">{{ copy('fieldFooterLeft') }}<AppInput v-model="profile.location_note[locale]" /></label>
            <label class="field-label">{{ copy('fieldFooterRight') }}<AppInput v-model="profile.availability_note[locale]" /></label>
            <label class="field-label">{{ copy('fieldQuote') }}<AppTextarea v-model="profile.quote[locale]" rows="2" /></label>
            <label class="field-label">{{ copy('fieldQuoteAuthor') }}<AppInput v-model="profile.quote_author[locale]" /></label>
        </template>
    </AppLanguageCards>
</template>
