<script setup>
import { onMounted, ref } from 'vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppInput from '../../components/ui/AppInput.vue';
import { copy } from '../../shared/i18n';
import { adminUrl } from '../../shared/admin-path';
import { apiFetch, errorMessage, reportError } from '../../shared/api';
import { useToast } from '../../shared/toast';
import { useConfirm } from '../../shared/confirm';

// Owns its own state and fetches: two-factor is self-contained, and the
// Insights page has no other reason to know about any of it.
const toast = useToast();
const { confirm } = useConfirm();

const state = ref(null);
const loading = ref(true);
const busy = ref(false);

// Shown once, at enrolment. Never re-fetched: the server does not hand the
// secret back after setup, which is the point of it being a secret.
const setup = ref(null);
const code = ref('');
const password = ref('');
const error = ref('');

const load = async () => {
    loading.value = true;

    try {
        state.value = await apiFetch(adminUrl('/two-factor'));
    } finally {
        loading.value = false;
    }
};

onMounted(() => load().catch(reportError));

const run = async (work) => {
    busy.value = true;
    error.value = '';

    try {
        await work();
    } catch (failure) {
        error.value = errorMessage(failure, copy('error'));
    } finally {
        busy.value = false;
    }
};

const begin = () => run(async () => {
    setup.value = await apiFetch(adminUrl('/two-factor'), { method: 'POST' });
    state.value = setup.value;
    code.value = '';
});

const confirmCode = () => run(async () => {
    state.value = await apiFetch(adminUrl('/two-factor/confirm'), {
        method: 'POST',
        body: { code: code.value },
    });
    code.value = '';
    toast.success(copy('twoFactorEnabled'));
});

const disable = () => run(async () => {
    state.value = await apiFetch(adminUrl('/two-factor'), {
        method: 'DELETE',
        body: { password: password.value },
    });
    password.value = '';
    setup.value = null;
    toast.success(copy('twoFactorDisabled'));
});

const newRecoveryCodes = async () => {
    if (!await confirm({ message: copy('twoFactorRecoveryHint'), confirmLabel: copy('twoFactorRecoveryNew') })) {
        return;
    }

    await run(async () => {
        const body = await apiFetch(adminUrl('/two-factor/recovery-codes'), { method: 'POST' });
        setup.value = { ...(setup.value ?? {}), recovery_codes: body.recovery_codes };
        await load();
    });
};
</script>

<template>
    <section class="two-factor">
        <h3 class="report-section-title">{{ copy('twoFactorTitle') }}</h3>

        <p v-if="loading" class="admin-note mt-3">{{ copy('securityLoading') }}</p>

        <template v-else-if="state">
            <p class="admin-note mt-3">
                {{ state.enabled ? copy('twoFactorOn') : (state.pending ? copy('twoFactorPending') : copy('twoFactorOff')) }}
            </p>

            <p v-if="error" class="mt-3 rounded-md border border-danger/40 bg-danger-bg px-4 py-3 text-sm text-danger-text">{{ error }}</p>

            <AppButton v-if="!state.enabled && !state.pending" variant="primary" size="sm" class="mt-4" :disabled="busy" @click="begin">
                {{ copy('twoFactorEnable') }}
            </AppButton>

            <!-- Enrolment in progress: the QR and the codes exist only in this
                 response, so they are shown while the component holds them. -->
            <div v-if="setup?.qr_data_uri" class="two-factor-setup mt-4">
                <img :src="setup.qr_data_uri" alt="" width="228" height="228" class="two-factor-qr">

                <div class="min-w-0">
                    <p class="text-sm leading-6 text-mute">{{ copy('twoFactorScan') }}</p>
                    <p class="mt-2 text-xs uppercase tracking-[0.08em] text-mute">{{ copy('twoFactorSetupKey') }}</p>
                    <code class="two-factor-key">{{ setup.setup_key }}</code>
                </div>
            </div>

            <form v-if="state.pending" class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="confirmCode">
                <label class="text-sm font-semibold">
                    {{ copy('twoFactorCode') }}
                    <AppInput v-model="code" class="mt-2" inputmode="numeric" autocomplete="one-time-code" required />
                </label>
                <AppButton variant="primary" size="sm" type="submit" :disabled="busy">
                    {{ busy ? copy('twoFactorConfirming') : copy('twoFactorConfirm') }}
                </AppButton>
            </form>

            <div v-if="setup?.recovery_codes" class="mt-5">
                <p class="text-xs uppercase tracking-[0.08em] text-mute">{{ copy('twoFactorRecovery') }}</p>
                <p class="mt-1 text-sm leading-6 text-mute">{{ copy('twoFactorRecoveryHint') }}</p>
                <ul class="two-factor-codes mt-2">
                    <li v-for="recovery in setup.recovery_codes" :key="recovery"><code>{{ recovery }}</code></li>
                </ul>
            </div>

            <template v-if="state.enabled">
                <p class="mt-5 text-sm text-mute">
                    <strong class="tabular-nums">{{ state.recovery_codes_left }}</strong> {{ copy('twoFactorRecoveryLeft') }}
                </p>

                <AppButton variant="secondary" size="sm" class="mt-2" :disabled="busy" @click="newRecoveryCodes">
                    {{ copy('twoFactorRecoveryNew') }}
                </AppButton>

                <form class="mt-5 flex flex-wrap items-end gap-3" @submit.prevent="disable">
                    <label class="text-sm font-semibold">
                        {{ copy('twoFactorPasswordToDisable') }}
                        <AppInput v-model="password" type="password" class="mt-2" autocomplete="current-password" required />
                    </label>
                    <AppButton variant="secondary" size="sm" type="submit" :disabled="busy">
                        {{ copy('twoFactorDisable') }}
                    </AppButton>
                </form>

                <p class="mt-5 text-xs leading-6 text-faint">
                    {{ copy('twoFactorLocked') }}
                    <code class="two-factor-key">php artisan two-factor:disable {{ state.email ?? '' }}</code>
                </p>
            </template>
        </template>
    </section>
</template>
