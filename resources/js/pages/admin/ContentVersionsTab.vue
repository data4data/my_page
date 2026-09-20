<script setup>
import { computed } from 'vue';
import { RefreshCcw, Undo2 } from '@lucide/vue';
import AppButton from '../../components/ui/AppButton.vue';
import { copy } from '../../shared/i18n';

const props = defineProps({
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

// One restore at a time — every button in the list locks while any of them
// is in flight, including the defaults row.
const busy = computed(() => props.restoring || props.restoringId !== null);

const formatDate = (value) => new Date(value).toLocaleString(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});
</script>

<template>
    <div class="flex flex-col gap-2.5">
        <p class="admin-note">{{ copy('historyHint') }}</p>

        <ul class="flex list-none flex-col gap-2.5 p-0">
            <!-- The shipped defaults are just another version to go back to,
                 so they lead the list rather than sitting in their own block. -->
            <li class="history-row history-row-defaults">
                <div class="history-meta">
                    <span class="history-time">
                        <RefreshCcw :size="14" aria-hidden="true" />
                        {{ copy('historyDefaults') }}
                    </span>
                    <span class="history-author">{{ copy('restoreHint') }}</span>
                </div>
                <AppButton variant="solid" :disabled="busy" @click="restoreDefaults">
                    <RefreshCcw :size="14" aria-hidden="true" />
                    {{ restoring ? copy('restoring') : copy('historyRestore') }}
                </AppButton>
            </li>

            <li v-if="revisionsLoading" class="history-row">
                <span class="history-author">{{ copy('historyLoading') }}</span>
            </li>
            <li v-else-if="revisions.length === 0" class="history-row">
                <span class="history-author">{{ copy('historyEmpty') }}</span>
            </li>

            <li v-for="revision in revisions" v-else :key="revision.id" class="history-row history-row-revision">
                <div class="history-meta">
                    <span class="history-time">{{ formatDate(revision.created_at) }}</span>
                    <!-- No author means the baseline snapshot taken before the
                         first ever save, not a missing person. -->
                    <span class="history-author">
                        <template v-if="revision.author">{{ copy('historyBy') }} {{ revision.author }}</template>
                        <template v-else>{{ copy('historyUnknown') }}</template>
                    </span>
                </div>
                <AppButton variant="outline" :disabled="busy" @click="restoreRevision(revision.id)">
                    <Undo2 :size="14" aria-hidden="true" />
                    {{ restoringId === revision.id ? copy('historyRestoring') : copy('historyRestore') }}
                </AppButton>
            </li>
        </ul>
    </div>
</template>
