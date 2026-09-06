<script setup>
import { onMounted, ref } from 'vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppCheckbox from '../../components/ui/AppCheckbox.vue';
import { apiFetch } from '../../shared/api';
import { adminUrl } from '../../shared/admin-path';
import { copy } from '../../shared/i18n';

const email = ref('');
const password = ref('');
const remember = ref(false);
const error = ref('');
const submitting = ref(false);
const initials = ref('');

// The password was right and two-factor is on, so the sign-in is half done:
// nothing is authenticated until the code below is accepted.
const awaitingCode = ref(false);
const usingRecoveryCode = ref(false);
const code = ref('');
const recoveryCode = ref('');

onMounted(async () => {
    // Public endpoint — just enough to show the real site initials instead
    // of a hardcoded name, so this page isn't tied to one specific project.
    // Decorative, so a failure here must not stop anyone signing in: on a
    // fresh install there is no seeded profile to read at all.
    initials.value = await apiFetch('/portfolio')
        .then((body) => body?.profile?.initials ?? '')
        .catch(() => '');
});

const submit = async () => {
    submitting.value = true;
    error.value = '';

    let body;

    try {
        body = await apiFetch(adminUrl('/login'), {
            method: 'POST',
            body: {
                email: email.value,
                password: password.value,
                remember: remember.value,
            },
            message: 'Could not sign in. Please try again.',
        });
    } catch (failure) {
        // apiFetch already prefers the field-level message, which for a bad
        // password is the one AuthController attaches to `email`.
        error.value = failure.message;
        submitting.value = false;
        return;
    }

    if (body.two_factor) {
        awaitingCode.value = true;
        submitting.value = false;

        return;
    }

    land(body);
};

const verify = async () => {
    submitting.value = true;
    error.value = '';

    try {
        land(await apiFetch(adminUrl('/two-factor-challenge'), {
            method: 'POST',
            body: usingRecoveryCode.value
                ? { recovery_code: recoveryCode.value }
                : { code: code.value },
            message: 'Could not verify that code. Please try again.',
        }));
    } catch (failure) {
        error.value = failure.message;
        submitting.value = false;
    }
};

// Full navigation (not a router push) so the freshly-set session cookie is
// picked up by Laravel's auth/role middleware on the next request. The
// fallback is the public page, not a guessed workspace path: this page is
// served to guests, so it is never told the prefix (see app.blade.php).
const land = (body) => {
    window.location.href = body.redirect ?? '/';
};
</script>

<template>
    <main class="min-h-screen bg-cream text-ink">
        <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6 py-10">
            <a href="/" class="text-3xl font-semibold tracking-normal">{{ initials }}</a>
            <h1 class="mt-3 font-serif text-3xl leading-tight">
                {{ awaitingCode ? copy('twoFactorChallengeTitle') : 'Sign in to edit the site' }}
            </h1>

            <p v-if="error" class="mt-6 rounded-md border border-danger/40 bg-danger-bg px-4 py-3 text-sm text-danger-text">{{ error }}</p>

            <form v-if="!awaitingCode" class="mt-8 space-y-5" @submit.prevent="submit">
                <label class="block text-sm font-semibold">
                    Email
                    <AppInput v-model="email" type="email" class="mt-2" required autofocus />
                </label>

                <label class="block text-sm font-semibold">
                    Password
                    <AppInput v-model="password" type="password" class="mt-2" required />
                </label>

                <AppCheckbox v-model="remember">Remember me</AppCheckbox>

                <AppButton variant="primary" type="submit" class="w-full justify-center" :disabled="submitting">
                    {{ submitting ? 'Signing in...' : 'Sign in' }}
                </AppButton>
            </form>

            <!-- Second step. The password is already accepted at this point,
                 but nothing is signed in until this is too. -->
            <form v-else class="mt-8 space-y-5" @submit.prevent="verify">
                <p class="text-sm leading-6 text-graphite">{{ copy('twoFactorChallengeHint') }}</p>

                <label v-if="!usingRecoveryCode" class="block text-sm font-semibold">
                    {{ copy('twoFactorCode') }}
                    <AppInput v-model="code" class="mt-2" inputmode="numeric" autocomplete="one-time-code" required autofocus />
                </label>

                <label v-else class="block text-sm font-semibold">
                    {{ copy('twoFactorRecoveryCode') }}
                    <AppInput v-model="recoveryCode" class="mt-2" autocomplete="off" required autofocus />
                </label>

                <AppButton variant="primary" type="submit" class="w-full justify-center" :disabled="submitting">
                    {{ submitting ? 'Signing in...' : copy('twoFactorVerify') }}
                </AppButton>

                <button type="button" class="text-link text-sm" @click="usingRecoveryCode = !usingRecoveryCode">
                    {{ usingRecoveryCode ? copy('twoFactorUseCode') : copy('twoFactorUseRecovery') }}
                </button>
            </form>

            <a href="/" class="text-link mt-8">&larr; Back to the public page</a>
        </div>
    </main>
</template>
