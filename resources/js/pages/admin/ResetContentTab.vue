<script setup>
import { RefreshCcw } from '@lucide/vue';
import AppButton from '../../components/ui/AppButton.vue';
import { copy } from '../../shared/i18n';

defineProps({
    restoring: {
        type: Boolean,
        required: true,
    },
    restoreDefaults: {
        type: Function,
        required: true,
    },
});
</script>

<template>
    <div>
        <div class="admin-note">{{ copy('restoreHint') }}</div>
        <div class="mt-5 flex justify-end">
            <AppButton variant="primary" :disabled="restoring" @click="restoreDefaults">
                <RefreshCcw :size="16" />
                {{ restoring ? copy('restoring') : copy('restore') }}
            </AppButton>
        </div>
    </div>
</template>
