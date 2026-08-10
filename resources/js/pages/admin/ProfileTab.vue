<script setup>
import AppInput from '../../components/ui/AppInput.vue';
import AppTranslatedField from '../../components/ui/AppTranslatedField.vue';

defineProps({
    profile: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="admin-grid">
        <div class="admin-full admin-note">
            These fields power the hero, quote, and footer on the public page. Change the initials, headline, summary, CTA links, and notes here.
        </div>

        <label class="admin-full">Initials<AppInput v-model="profile.initials" maxlength="12" /></label>

        <div class="admin-full"><AppTranslatedField label="Role" v-model="profile.role" /></div>
        <div class="admin-full"><AppTranslatedField label="Headline" v-model="profile.headline" multiline rows="2" /></div>
        <div class="admin-full"><AppTranslatedField label="Summary" v-model="profile.summary" multiline rows="3" /></div>
        <div class="admin-full"><AppTranslatedField label="Primary CTA label" v-model="profile.primary_cta_label" /></div>

        <label class="admin-full">Primary CTA URL<AppInput v-model="profile.primary_cta_url" /></label>

        <div class="admin-full"><AppTranslatedField label="Secondary CTA label" v-model="profile.secondary_cta_label" /></div>

        <label class="admin-full">Secondary CTA URL<AppInput v-model="profile.secondary_cta_url" /></label>

        <div class="admin-full"><AppTranslatedField label="Location note" v-model="profile.location_note" /></div>
        <div class="admin-full"><AppTranslatedField label="Availability note" v-model="profile.availability_note" /></div>
        <div class="admin-full"><AppTranslatedField label="Quote text" v-model="profile.quote" multiline rows="2" /></div>
        <div class="admin-full"><AppTranslatedField label="Quote author" v-model="profile.quote_author" /></div>
    </div>
</template>
