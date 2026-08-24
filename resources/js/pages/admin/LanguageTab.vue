<script setup>
import { computed } from 'vue';
import { Eye, EyeOff } from '@lucide/vue';
import { copy, LANGUAGES } from '../../shared/i18n';

const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },
});

const isDefault = (value) => (props.profile.default_language || 'en') === value;

// A two-option segmented control rather than checkboxes: exactly one language
// is the default, so picking one implicitly unpicks the other and the site
// can never end up with no default at all.
const makeDefault = (value) => {
    props.profile.default_language = value;
};

const switcherShown = computed(() => props.profile.show_language_toggle !== false);

const toggleSwitcher = () => {
    props.profile.show_language_toggle = !switcherShown.value;
};
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="language-row">
            <span class="flex-1 text-sm text-ink">{{ copy('languageDefaultLabel') }}</span>

            <div class="language-segment" role="group" :aria-label="copy('languageDefaultLabel')">
                <button
                    v-for="language in LANGUAGES"
                    :key="language.value"
                    type="button"
                    class="language-segment-option"
                    :class="{ active: isDefault(language.value) }"
                    :aria-pressed="isDefault(language.value)"
                    @click="makeDefault(language.value)"
                >
                    {{ language.label }}
                </button>
            </div>
        </div>

        <div class="language-row">
            <span class="flex-1 text-sm text-ink">{{ copy('languageSwitcherLabel') }}</span>

            <button
                type="button"
                class="language-eye"
                :class="{ 'language-eye-off': !switcherShown }"
                :aria-pressed="switcherShown"
                :title="copy('languageToggleVisibility')"
                :aria-label="copy('languageToggleVisibility')"
                @click="toggleSwitcher"
            >
                <component :is="switcherShown ? Eye : EyeOff" :size="16" />
            </button>
        </div>

        <p class="mt-1 text-xs leading-5 text-taupe">{{ copy('languageVisibilityHint') }}</p>
    </div>
</template>
