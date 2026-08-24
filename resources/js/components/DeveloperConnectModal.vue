<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { X } from '@lucide/vue';
import AppInput from './ui/AppInput.vue';
import AppTextarea from './ui/AppTextarea.vue';
import AppButton from './ui/AppButton.vue';
import { copy } from '../shared/i18n';
import { csrfToken } from '../shared/portfolio';

const router = useRouter();

const form = ref({
    name: '',
    email: '',
    message: '',
    company: '',
    portfolio_url: '',
    linkedin_url: '',
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

const close = () => router.push({ name: 'public' });

const submit = async () => {
    attemptedSubmit.value = true;
    error.value = '';

    if (hasClientErrors.value) {
        error.value = copy('connectValidation');
        return;
    }

    submitting.value = true;

    const response = await fetch('/hi-developer', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(form.value),
    });

    if (!response.ok) {
        const body = await response.json().catch(() => null);
        const firstError = body?.errors ? Object.values(body.errors)[0]?.[0] : null;
        error.value = firstError ?? body?.message ?? copy('connectError');
        submitting.value = false;
        return;
    }

    submitted.value = true;
    submitting.value = false;
};
</script>

<template>
    <div class="connect-overlay" @click.self="close">
        <div class="connect-modal">
            <button type="button" class="connect-close" :aria-label="copy('connectClose')" @click="close">
                <X :size="18" />
            </button>

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

                    <AppButton variant="primary" type="submit" class="w-full justify-center" :disabled="submitting">
                        {{ submitting ? copy('connectSubmitting') : copy('connectSubmit') }}
                    </AppButton>
                </form>
            </template>
        </div>
    </div>
</template>
