<script setup>
import { computed } from 'vue';
import { copy } from '../../shared/i18n';

const props = defineProps({
    events: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

// copy() returns a plain string, so the two counts it carries are filled in
// here rather than adding a formatting layer to i18n for two placeholders.
const fill = (key, values) => Object.entries(values).reduce(
    (text, [name, value]) => text.replace(`{${name}}`, value),
    copy(key),
);

const outcomeLabel = {
    login_succeeded: 'securitySucceeded',
    login_failed: 'securityFailed',
    login_blocked: 'securityBlocked',
};

const totals = computed(() => {
    const counts = props.events?.totals ?? {};

    return [
        { key: 'succeeded', label: copy('securitySucceeded'), count: counts.succeeded ?? 0 },
        { key: 'failed', label: copy('securityFailed'), count: counts.failed ?? 0 },
        { key: 'blocked', label: copy('securityBlocked'), count: counts.blocked ?? 0 },
    ];
});

const addresses = computed(() => props.events?.by_address ?? []);
const recent = computed(() => props.events?.recent ?? []);

const formatDate = (value) => new Date(value).toLocaleString(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});
</script>

<template>
    <div class="space-y-4">
        <div class="admin-note">
            {{ fill('securityInfo', { days: props.events?.retention_days ?? 30 }) }}
        </div>

        <p v-if="loading" class="admin-note">{{ copy('securityLoading') }}</p>

        <template v-else-if="events">
            <section>
                <h3 class="report-section-title">{{ fill('securityWindow', { hours: events.window_hours }) }}</h3>
                <ul class="mt-3 flex flex-wrap gap-2">
                    <li v-for="total in totals" :key="total.key" class="report-status-chip">
                        {{ total.label }}
                        <strong class="tabular-nums">{{ total.count }}</strong>
                    </li>
                </ul>
            </section>

            <section>
                <h3 class="report-section-title">{{ copy('securityByAddress') }}</h3>

                <p v-if="addresses.length === 0" class="admin-note mt-3">{{ copy('securityQuiet') }}</p>

                <!-- Own scroll container: an address column can be a full
                     IPv6 literal, and the panel must never scroll sideways. -->
                <div v-else class="mt-3 overflow-x-auto">
                    <table class="security-table">
                        <thead>
                            <tr>
                                <th>{{ copy('securityAddress') }}</th>
                                <th class="text-right">{{ copy('securityAttempts') }}</th>
                                <th class="text-right">{{ copy('securityFailed') }}</th>
                                <th class="text-right">{{ copy('securityBlocked') }}</th>
                                <th>{{ copy('securityLastSeen') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in addresses" :key="row.ip_address ?? 'unknown'">
                                <td class="font-mono text-xs">{{ row.ip_address ?? copy('securityUnknownAddress') }}</td>
                                <td class="text-right tabular-nums">{{ row.attempts }}</td>
                                <td class="text-right tabular-nums">{{ row.failed }}</td>
                                <td class="text-right tabular-nums">{{ row.blocked }}</td>
                                <td class="whitespace-nowrap text-xs text-taupe">{{ formatDate(row.last_seen) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section>
                <h3 class="report-section-title">{{ copy('securityRecent') }}</h3>

                <p v-if="recent.length === 0" class="admin-note mt-3">{{ copy('securityEmpty') }}</p>

                <ul v-else class="mt-3 flex flex-col gap-2">
                    <li v-for="event in recent" :key="event.id" class="security-row" :class="`security-row-${event.type}`">
                        <span class="security-row-outcome">{{ copy(outcomeLabel[event.type]) }}</span>
                        <span class="font-mono text-xs">{{ event.ip_address ?? copy('securityUnknownAddress') }}</span>
                        <span class="truncate text-xs text-taupe">{{ event.email }}</span>
                        <span class="whitespace-nowrap text-xs text-taupe">{{ formatDate(event.created_at) }}</span>
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>
