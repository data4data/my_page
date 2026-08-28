<script setup>
import { History, RefreshCcw, Undo2 } from '@lucide/vue';
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
    revisions: {
        type: Array,
        required: true,
    },
    revisionsLoading: {
        type: Boolean,
        required: true,
    },
    restoreRevision: {
        type: Function,
        required: true,
    },
    restoringId: {
        type: Number,
        default: null,
    },
});

const formatDate = (value) => new Date(value).toLocaleString(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});
</script>

<template>
    <div class="space-y-8">
        <section>
            <div class="admin-note">{{ copy('restoreHint') }}</div>
            <div class="mt-5 flex justify-end">
                <AppButton variant="primary" :disabled="restoring" @click="restoreDefaults">
                    <RefreshCcw :size="16" />
                    {{ restoring ? copy('restoring') : copy('restore') }}
                </AppButton>
            </div>
        </section>

        <section>
            <h3 class="history-heading">
                <History :size="16" />
                {{ copy('historyTitle') }}
            </h3>
            <div class="admin-note">{{ copy('historyHint') }}</div>

            <p v-if="revisionsLoading" class="admin-note mt-4">{{ copy('historyLoading') }}</p>
            <p v-else-if="revisions.length === 0" class="admin-note mt-4">{{ copy('historyEmpty') }}</p>

            <ul v-else class="history-list">
                <li v-for="revision in revisions" :key="revision.id" class="history-row">
                    <div class="history-meta">
                        <span class="history-time">{{ formatDate(revision.created_at) }}</span>
                        <!-- No author means the baseline snapshot taken before
                             the first ever save, not a missing person. -->
                        <span class="history-author">
                            <template v-if="revision.author">{{ copy('historyBy') }} {{ revision.author }}</template>
                            <template v-else>{{ copy('historyUnknown') }}</template>
                        </span>
                    </div>
                    <AppButton
                        variant="secondary"
                        size="sm"
                        :disabled="restoringId !== null"
                        @click="restoreRevision(revision.id)"
                    >
                        <Undo2 :size="15" />
                        {{ restoringId === revision.id ? copy('historyRestoring') : copy('historyRestore') }}
                    </AppButton>
                </li>
            </ul>
        </section>
    </div>
</template>
