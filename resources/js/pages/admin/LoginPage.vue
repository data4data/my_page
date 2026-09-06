<script setup>
import { onMounted, ref } from 'vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppCheckbox from '../../components/ui/AppCheckbox.vue';
import { csrfToken } from '../../shared/portfolio';

const email = ref('');
const password = ref('');
const remember = ref(false);
const error = ref('');
const submitting = ref(false);
const initials = ref('');

onMounted(async () => {
    // Public endpoint — just enough to show the real site initials instead
    // of a hardcoded name, so this page isn't tied to one specific project.
    const response = await fetch('/portfolio');
    const body = await response.json().catch(() => null);
    initials.value = body?.profile?.initials ?? '';
});

const submit = async () => {
    submitting.value = true;
    error.value = '';

    const response = await fetch('/login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
            email: email.value,
            password: password.value,
            remember: remember.value,
        }),
    });

    if (!response.ok) {
        const body = await response.json().catch(() => null);
        error.value = body?.errors?.email?.[0] ?? body?.message ?? 'Could not sign in. Please try again.';
        submitting.value = false;
        return;
    }

    const body = await response.json();
    // Full navigation (not a router push) so the freshly-set session cookie
    // is picked up by Laravel's auth/role middleware on the next request.
    // The fallback is the public page, not a guessed workspace path: this page
    // is served to guests, so it is never told the prefix (see app.blade.php).
    window.location.href = body.redirect ?? '/';
};
</script>

<template>
    <main class="min-h-screen bg-cream text-ink">
        <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6 py-10">
            <a href="/" class="text-3xl font-semibold tracking-normal">{{ initials }}</a>
            <h1 class="mt-3 font-serif text-3xl leading-tight">Sign in to edit the site</h1>

            <p v-if="error" class="mt-6 rounded-md border border-danger/40 bg-danger-bg px-4 py-3 text-sm text-danger-text">{{ error }}</p>

            <form class="mt-8 space-y-5" @submit.prevent="submit">
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

            <a href="/" class="text-link mt-8">&larr; Back to the public page</a>
        </div>
    </main>
</template>
