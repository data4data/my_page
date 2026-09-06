<script setup>
import { computed } from 'vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppTranslatedField from '../../components/ui/AppTranslatedField.vue';

const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },
});

// Stored as one flat [{text, tone}] list, edited as two comma-separated
// fields — the same shape-and-split pattern the project tags use. Both
// languages' words live in the same list, since only the ones appearing in
// the headline actually on screen can match.
const TONES = ['blue', 'gold'];

const highlights = computed(() => (Array.isArray(props.profile.headline_highlights) ? props.profile.headline_highlights : []));

const termsFor = (tone) => highlights.value.filter((item) => item?.tone === tone).map((item) => item.text).join(', ');

const setTerms = (tone, value) => {
    const kept = highlights.value.filter((item) => item?.tone !== tone);
    const added = value.split(',').map((text) => text.trim()).filter(Boolean).map((text) => ({ text, tone }));

    // Rebuilt in tone order so the stored list stays stable between saves
    // rather than reshuffling on every keystroke.
    props.profile.headline_highlights = TONES.flatMap(
        (each) => (each === tone ? added : kept.filter((item) => item.tone === each)),
    );
};
</script>

<template>
    <div class="admin-grid">
        <div class="admin-full admin-note">
            These fields power the hero, quote, and footer on the public page. Change the initials, headline, summary, CTA links, and notes here.
        </div>

        <label class="admin-full">Initials<AppInput v-model="profile.initials" maxlength="12" /></label>

        <div class="admin-full"><AppTranslatedField label="Role" v-model="profile.role" /></div>
        <div class="admin-full"><AppTranslatedField label="Headline" v-model="profile.headline" multiline rows="2" /></div>

        <div class="admin-full admin-note">
            Words from the headline to accent, comma separated. List each language's spelling — only the ones in the headline currently shown will match.
        </div>

        <label>Accented blue<AppInput :model-value="termsFor('blue')" @update:model-value="setTerms('blue', $event)" /></label>
        <label>Accented gold<AppInput :model-value="termsFor('gold')" @update:model-value="setTerms('gold', $event)" /></label>
        <div class="admin-full"><AppTranslatedField label="Summary" v-model="profile.summary" multiline rows="3" /></div>
        <div class="admin-full"><AppTranslatedField label="Primary CTA label" v-model="profile.primary_cta_label" /></div>

        <label class="admin-full">Primary CTA URL<AppInput v-model="profile.primary_cta_url" /></label>

        <div class="admin-full"><AppTranslatedField label="Secondary CTA label" v-model="profile.secondary_cta_label" /></div>

        <label class="admin-full">Secondary CTA URL<AppInput v-model="profile.secondary_cta_url" /></label>

        <div class="admin-full admin-note">
            The two footer notes sit either side of your social links, at the very bottom of the public page.
        </div>

        <div class="admin-full"><AppTranslatedField label="Footer note (left)" v-model="profile.location_note" /></div>
        <div class="admin-full"><AppTranslatedField label="Footer note (right)" v-model="profile.availability_note" /></div>
        <div class="admin-full"><AppTranslatedField label="Quote text" v-model="profile.quote" multiline rows="2" /></div>
        <div class="admin-full"><AppTranslatedField label="Quote author" v-model="profile.quote_author" /></div>
    </div>
</template>
