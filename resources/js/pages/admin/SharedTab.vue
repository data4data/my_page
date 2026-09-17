<script setup>
import { computed } from 'vue';
import AppInput from '../../components/ui/AppInput.vue';
import { copy } from '../../shared/i18n';

// The values on the profile that are the same in both languages. Split out of
// the Profile tab, which now holds only translated copy — mixing the two meant
// a URL field sitting between an English headline and its Dutch twin.
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
    <div class="flex flex-col gap-3.5">
        <div class="grid gap-3 md:grid-cols-[10rem_minmax(0,1fr)_minmax(0,1fr)]">
            <label class="field-label">
                {{ copy('fieldInitials') }}
                <AppInput v-model="profile.initials" maxlength="12" />
            </label>

            <label class="field-label">
                {{ copy('fieldPrimaryCtaUrl') }}
                <AppInput v-model="profile.primary_cta_url" />
            </label>

            <label class="field-label">
                {{ copy('fieldSecondaryCtaUrl') }}
                <AppInput v-model="profile.secondary_cta_url" />
            </label>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            <label class="field-label">
                {{ copy('fieldAccentBlue') }}
                <AppInput :model-value="termsFor('blue')" @update:model-value="setTerms('blue', $event)" />
            </label>

            <label class="field-label">
                {{ copy('fieldAccentGold') }}
                <AppInput :model-value="termsFor('gold')" @update:model-value="setTerms('gold', $event)" />
            </label>
        </div>

        <p class="admin-note">{{ copy('sharedAccentHint') }}</p>
    </div>
</template>
