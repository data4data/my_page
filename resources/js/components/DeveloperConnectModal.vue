<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppModal from './ui/AppModal.vue';
import AppInput from './ui/AppInput.vue';
import AppTextarea from './ui/AppTextarea.vue';
import AppButton from './ui/AppButton.vue';
import { copy } from '../shared/i18n';
import { apiFetch } from '../shared/api';
import { barePath, localeFromPath } from '../shared/i18n';

const router = useRouter();
const route = useRoute();

const form = ref({
    name: '',
    email: '',
    message: '',
    company: '',
    portfolio_url: '',
    linkedin_url: '',
    // Honeypot. Hidden from people and from screen readers, so anything that
    // arrives here came from a bot filling every input.
    website: '',
});
const submitting = ref(false);
const submitted = ref(false);
const error = ref('');

// True after the first submit attempt, so red borders only show once the
// visitor has tried to send the form.
const attemptedSubmit = ref(false);

const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());

const nameInvalid = computed(() => attemptedSubmit.value && !form.value.name.trim());
const emailInvalid = computed(() => attemptedSubmit.value && !isValidEmail(form.value.email));
const messageInvalid = computed(() => attemptedSubmit.value && !form.value.message.trim());
const hasClientErrors = computed(() => nameInvalid.value || emailInvalid.value || messageInvalid.value);

// Back to the page behind the popup, in the language it was opened in.
const close = () => router.push(barePath(route.path) === '/hi-developer' && localeFromPath(route.path)
    ? `/${localeFromPath(route.path)}`
    : '/');

const submit = async () => {
    attemptedSubmit.value = true;
    error.value = '';

    if (hasClientErrors.value) {
        error.value = copy('connectValidation');
        return;
    }

    submitting.value = true;

    try {
        // Posted to the localised URL so the server's own validation messages come
    // back in the language the visitor is reading.
    await apiFetch(localeFromPath(route.path) ? `/${localeFromPath(route.path)}/hi-developer` : '/hi-developer', {
            method: 'POST',
            body: form.value,
            message: copy('connectError'),
        });

        submitted.value = true;
    } catch (failure) {
        error.value = failure.message;
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <AppModal :label="copy('connectTitle')" @close="close">
        <template v-if="submitted">
            <p class="eyebrow">{{ copy('forDevelopers') }}</p>
            <h2 class="mt-3 font-serif text-2xl leading-tight">{{ copy('connectSuccess') }}</h2>
            <AppButton variant="secondary" class="mt-6" @click="close">{{ copy('connectClose') }}</AppButton>
        </template>

        <template v-else>
            <p class="eyebrow">{{ copy('forDevelopers') }}</p>
            <h2 class="mt-3 font-serif text-2xl leading-tight">{{ copy('connectTitle') }}</h2>
            <p class="mt-3 text-sm leading-6 text-graphite">{{ copy('connectCopy') }}</p>

            <p v-if="error" class="mt-4 rounded-md border border-danger/40 bg-danger-bg px-4 py-3 text-sm text-danger-text">{{ error }}</p>

            <form class="mt-6 space-y-4" novalidate @submit.prevent="submit">
                <label class="block text-sm font-semibold">
                    {{ copy('connectName') }}<span class="field-required-mark">*</span>
                    <AppInput v-model="form.name" class="mt-2" :invalid="nameInvalid" />
                </label>
                <label class="block text-sm font-semibold">
                    {{ copy('connectEmail') }}<span class="field-required-mark">*</span>
                    <AppInput v-model="form.email" type="email" class="mt-2" :invalid="emailInvalid" />
                </label>
                <label class="block text-sm font-semibold">
                    {{ copy('connectCompany') }}
                    <AppInput v-model="form.company" class="mt-2" />
                </label>
                <label class="block text-sm font-semibold">
                    {{ copy('connectPortfolio') }}
                    <AppInput v-model="form.portfolio_url" type="url" class="mt-2" />
                </label>
                <label class="block text-sm font-semibold">
                    {{ copy('connectLinkedin') }}
                    <AppInput v-model="form.linkedin_url" type="url" class="mt-2" />
                </label>
                <label class="block text-sm font-semibold">
                    {{ copy('connectMessage') }}<span class="field-required-mark">*</span>
                    <AppTextarea v-model="form.message" rows="4" class="mt-2" :invalid="messageInvalid" />
                </label>

                <!-- Honeypot. Positioned off-screen rather than display:none,
                     which bots learned to skip years ago, and hidden from
                     assistive tech so nobody is asked to fill it in. -->
                <div class="honeypot" aria-hidden="true">
                    <label>
                        Website
                        <input v-model="form.website" type="text" name="website" tabindex="-1" autocomplete="off">
                    </label>
                </div>

                <AppButton variant="primary" type="submit" class="w-full justify-center" :disabled="submitting">
                    {{ submitting ? copy('connectSubmitting') : copy('connectSubmit') }}
                </AppButton>
            </form>
        </template>
    </AppModal>
</template>
