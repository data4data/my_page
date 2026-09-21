<script setup>
import { computed, onMounted, ref } from 'vue';
import { Eye, EyeOff } from '@lucide/vue';
import AppPillSwitch from '../../../components/ui/AppPillSwitch.vue';
import AppSelect from '../../../components/ui/AppSelect.vue';
import { copy, LANGUAGES } from '../../../shared/i18n';
import { adminUrl } from '../../../shared/admin-path';
import { apiFetch, reportError } from '../../../shared/api';
import { useToast } from '../../../shared/toast';

const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },
});

const toast = useToast();

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

// The timezone is the one row here that is not page content: it belongs to the
// signed-in owner, so it loads and saves through its own endpoint rather than
// riding along in the payload the Save button writes.
const zone = ref(null);
const zones = ref([]);
const loadingZones = ref(true);
const savingZone = ref(false);

onMounted(async () => {
    try {
        const body = await apiFetch(adminUrl('/timezone'));
        zone.value = body.timezone;
        // The server sends the list, so the picker can only offer a value the
        // save would accept. Underscores read badly in a menu.
        zones.value = body.options.map((name) => ({ value: name, label: name.replace(/_/g, ' ') }));
    } catch (failure) {
        reportError(failure, copy('timezoneLoadError'));
    } finally {
        loadingZones.value = false;
    }
});

const timezone = computed({
    get: () => zone.value,
    set: (value) => {
        const previous = zone.value;
        zone.value = value;
        savingZone.value = true;

        apiFetch(adminUrl('/timezone'), { method: 'PUT', body: { timezone: value } })
            .then(() => toast.success(copy('timezoneSaved')))
            .catch((failure) => {
                // Back to what the server still holds: a row showing a value
                // that was refused would report in a zone nothing uses.
                zone.value = previous;
                reportError(failure, copy('timezoneSaveError'));
            })
            .finally(() => {
                savingZone.value = false;
            });
    },
});
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

        <div class="setting-row">
            <div class="setting-row-text">
                <span class="setting-row-name">{{ copy('timezoneLabel') }}</span>
                <span class="setting-row-hint">{{ copy('timezoneHint') }}</span>
            </div>

            <AppSelect
                v-model="timezone"
                class="setting-row-field"
                filterable
                :options="zones"
                :filter-placeholder="copy('timezoneSearch')"
                :disabled="loadingZones || savingZone"
                :aria-label="copy('timezoneLabel')"
            />
        </div>
    </div>
</template>
