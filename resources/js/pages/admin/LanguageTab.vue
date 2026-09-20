<script setup>
import { computed } from 'vue';
import { Eye, EyeOff } from '@lucide/vue';
import AppPillSwitch from '../../components/ui/AppPillSwitch.vue';
import { copy, LANGUAGES } from '../../shared/i18n';

const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },
});

// A switch, not checkboxes: exactly one language is the default, so the site
// can never end up with none.
const defaultLanguage = computed({
    get: () => props.profile.default_language || 'en',
    set: (value) => {
        props.profile.default_language = value;
    },
});

const switcherShown = computed({
    get: () => props.profile.show_language_toggle !== false,
    set: (value) => {
        props.profile.show_language_toggle = value;
    },
});

const languageOptions = computed(() => LANGUAGES.map((language) => ({
    value: language.value,
    label: language.value.toUpperCase(),
    ariaLabel: language.label,
})));

// Shown/hidden rather than on/off: the eye says which state you are in.
const switcherOptions = computed(() => [
    { value: true, icon: Eye, ariaLabel: copy('languageSwitcherShown') },
    { value: false, icon: EyeOff, ariaLabel: copy('languageSwitcherHidden') },
]);
</script>

<template>
    <div class="flex flex-col gap-2.5">
        <div class="setting-row">
            <div class="setting-row-text">
                <span class="setting-row-name">{{ copy('languageDefaultLabel') }}</span>
                <span class="setting-row-hint">{{ copy('languageDefaultHint') }}</span>
            </div>

            <AppPillSwitch
                v-model="defaultLanguage"
                :options="languageOptions"
                :aria-label="copy('languageDefaultLabel')"
            />
        </div>

        <div class="setting-row">
            <div class="setting-row-text">
                <span class="setting-row-name">{{ copy('languageSwitcherLabel') }}</span>
                <span class="setting-row-hint">{{ copy('languageVisibilityHint') }}</span>
            </div>

            <AppPillSwitch
                v-model="switcherShown"
                :options="switcherOptions"
                :aria-label="copy('languageToggleVisibility')"
            />
        </div>
    </div>
</template>
