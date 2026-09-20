<script setup>
import { computed, ref } from 'vue';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import AppButton from '../../../components/ui/AppButton.vue';
import AppTextarea from '../../../components/ui/AppTextarea.vue';
import AppPillSwitch from '../../../components/ui/AppPillSwitch.vue';
import { reportError } from '../../../shared/api';
import { useToast } from '../../../shared/toast';
import {
    usePlanning,
    startOfWeek,
    startOfMonth,
    addDays,
    addMonths,
    formatMinutes,
    TASK_STATUSES,
    taskStatusLabelKey,
} from '../../../shared/planning';
import { copy, lang } from '../../../shared/i18n';

const { fetchReport, fetchReflection, saveReflection } = usePlanning();
const toast = useToast();

// Self-contained period state: reports summarise a whole week or month,
// which doesn't map onto the calendar's day/week/month view modes, so this
// keeps its own selector rather than piggy-backing on CalendarView's.
const periodType = ref('week');
const periodStart = ref(startOfWeek(new Date()));
const report = ref(null);
const loading = ref(false);
const failed = ref(false);

const locale = computed(() => (lang.value === 'nl' ? 'nl-NL' : 'en-US'));
const capitalize = (value) => value.charAt(0).toUpperCase() + value.slice(1);

const periodLabel = computed(() => {
    if (periodType.value === 'month') {
        return capitalize(periodStart.value.toLocaleDateString(locale.value, { month: 'long', year: 'numeric' }));
    }

    const format = (date) => date.toLocaleDateString(locale.value, { day: 'numeric', month: 'short' });
    return `${format(periodStart.value)} – ${format(addDays(periodStart.value, 6))}`;
});

const reflectionNotes = ref('');
const savingReflection = ref(false);

const load = async () => {
    loading.value = true;
    failed.value = false;

    try {
        report.value = await fetchReport(periodType.value, periodStart.value);

        // Reuse the period the backend derived for the report, so the
        // reflection is always keyed to exactly the range being summarised.
        const reflection = await fetchReflection(periodType.value, report.value.period_start, report.value.period_end);
        reflectionNotes.value = reflection?.notes ?? '';
    } catch (error) {
        report.value = null;
        reflectionNotes.value = '';
        failed.value = true;
        // The panel shows its own "could not load" state, so this adds the
        // reason rather than repeating that something went wrong.
        reportError(error, copy('reportError'));
    } finally {
        loading.value = false;
    }
};

const storeReflection = async () => {
    savingReflection.value = true;

    try {
        await saveReflection({
            period_type: periodType.value,
            period_start: report.value.period_start,
            period_end: report.value.period_end,
            notes: reflectionNotes.value || null,
        });
        toast.success(copy('reflectionSaved'));
    } catch (error) {
        reportError(error, copy('reflectionError'));
    } finally {
        savingReflection.value = false;
    }
};

const setPeriodType = (type) => {
    if (type === periodType.value) {
        return;
    }

    periodType.value = type;
    periodStart.value = type === 'week' ? startOfWeek(periodStart.value) : startOfMonth(periodStart.value);
    load();
};

const shiftPeriod = (direction) => {
    periodStart.value = periodType.value === 'week'
        ? addDays(periodStart.value, 7 * direction)
        : addMonths(periodStart.value, direction);
    load();
};

const goToCurrent = () => {
    periodStart.value = periodType.value === 'week' ? startOfWeek(new Date()) : startOfMonth(new Date());
    load();
};

// One bar per category, read as progress against the plan: the track is the
// planned time, the fill is what was actually tracked. Two separate bars
// forced a left-to-right comparison of lengths to answer "did I do what I
// planned"; this answers it directly, and the absolute minutes stay in the
// row's label so magnitude isn't lost.
const categoryRows = computed(() => [...(report.value?.by_category ?? [])]
    .map((row) => {
        const planned = row.planned_minutes ?? 0;
        const tracked = row.minutes ?? 0;
        const ratio = planned > 0 ? tracked / planned : 0;

        return {
            ...row,
            // The report groups by id and leaves the label to the client, so
            // the uncategorized bucket is named here rather than in English
            // on the server.
            label: row.category ?? copy('uncategorized'),
            planned,
            tracked,
            // No plan to measure against, so a percentage would be meaningless.
            percent: planned > 0 ? Math.round(ratio * 100) : null,
            fillWidth: planned > 0
                ? `${Math.min(100, Math.round(ratio * 100))}%`
                : (tracked > 0 ? '100%' : '0%'),
            overBy: planned > 0 && tracked > planned ? tracked - planned : 0,
            unplanned: planned === 0 && tracked > 0,
        };
    })
    .sort((a, b) => b.planned - a.planned || b.tracked - a.tracked));

const rowTooltip = (row) => {
    const parts = [
        `${copy('reportActualLabel')}: ${formatMinutes(row.tracked)}`,
        `${copy('reportPlannedLabel')}: ${formatMinutes(row.planned)}`,
    ];

    if (row.percent !== null) {
        parts.push(`${row.percent}%`);
    }

    if (row.overBy > 0) {
        parts.push(`+${formatMinutes(row.overBy)} ${copy('reportOver')}`);
    }

    if (row.unplanned) {
        parts.push(copy('reportUnplanned'));
    }

    return parts.join(' · ');
};

const statusRows = computed(() => TASK_STATUSES
    .map((status) => ({ status, label: copy(taskStatusLabelKey[status]), count: report.value?.by_status?.[status] ?? 0 }))
    .filter((row) => row.count > 0));

load();

// Computed so the two labels follow the EN/NL switch like every other string.
const periodOptions = computed(() => [
    { value: 'week', label: copy('week') },
    { value: 'month', label: copy('month') },
]);
</script>

<template>
    <div>
        <div class="admin-toolbar">
            <div class="period-nav">
                <button type="button" class="period-nav-button" :aria-label="copy('previousPeriod')" @click="shiftPeriod(-1)">
                    <ChevronLeft :size="15" aria-hidden="true" />
                </button>
                <button type="button" class="period-nav-label" @click="goToCurrent">{{ periodLabel }}</button>
                <button type="button" class="period-nav-button" :aria-label="copy('nextPeriod')" @click="shiftPeriod(1)">
                    <ChevronRight :size="15" aria-hidden="true" />
                </button>
            </div>

            <div class="admin-toolbar-end">
                <AppPillSwitch
                    :options="periodOptions"
                    :model-value="periodType"
                    :aria-label="copy('report')"
                    @update:model-value="setPeriodType"
                />
            </div>
        </div>

        <p v-if="loading" class="admin-note mt-4">{{ copy('loading') }}</p>
        <p v-else-if="failed" class="admin-note mt-4">{{ copy('reportError') }}</p>

        <template v-else-if="report">
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="report-stat">
                    <span class="report-stat-label">{{ copy('reportPlannedTime') }}</span>
                    <strong class="report-stat-value">{{ formatMinutes(report.total_planned_minutes) }}</strong>
                </div>
                <div class="report-stat">
                    <span class="report-stat-label">{{ copy('reportTotalTime') }}</span>
                    <strong class="report-stat-value">{{ formatMinutes(report.total_minutes) }}</strong>
                </div>
                <div class="report-stat">
                    <span class="report-stat-label">{{ copy('reportTaskCount') }}</span>
                    <strong class="report-stat-value">{{ report.task_count }}</strong>
                </div>
            </div>

            <section class="mt-6">
                <h3 class="report-section-title">{{ copy('reportByCategory') }}</h3>

                <p v-if="categoryRows.length === 0" class="week-day-empty mt-3">{{ copy('reportEmpty') }}</p>

                <template v-else>
                    <p class="mt-1 text-xs leading-5 text-faint">{{ copy('reportBarHint') }}</p>

                    <ul class="mt-3 flex flex-col gap-4">
                        <li v-for="row in categoryRows" :key="row.category_id ?? 'uncategorized'" :title="rowTooltip(row)">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="text-sm text-body">{{ row.label }}</span>
                                <span class="text-xs tabular-nums text-faint">
                                    {{ formatMinutes(row.tracked) }} / {{ formatMinutes(row.planned) }}
                                    <template v-if="row.percent !== null"> · {{ row.percent }}%</template>
                                    <template v-else> · {{ copy('reportUnplanned') }}</template>
                                </span>
                            </div>

                            <div class="report-bar-track">
                                <div
                                    class="report-bar-fill"
                                    :class="{ 'report-bar-fill-over': row.overBy > 0 }"
                                    :style="{ width: row.fillWidth, background: row.overBy > 0 ? null : row.color }"
                                ></div>
                            </div>

                            <p v-if="row.overBy > 0" class="mt-1 text-[11px] text-danger-text">
                                +{{ formatMinutes(row.overBy) }} {{ copy('reportOver') }}
                            </p>
                        </li>
                    </ul>
                </template>
            </section>

            <section v-if="statusRows.length" class="mt-6">
                <h3 class="report-section-title">{{ copy('reportByStatus') }}</h3>
                <ul class="mt-3 flex flex-wrap gap-2">
                    <li v-for="row in statusRows" :key="row.status" class="report-status-chip" :class="`status-${row.status}`">
                        {{ row.label }}
                        <strong class="tabular-nums">{{ row.count }}</strong>
                    </li>
                </ul>
            </section>

            <section class="mt-6">
                <h3 class="report-section-title">{{ copy('reflectionTitle') }}</h3>
                <p class="mt-1 text-xs leading-5 text-faint">{{ copy('reflectionHint') }}</p>
                <AppTextarea v-model="reflectionNotes" class="mt-3" rows="4" :placeholder="copy('reflectionPlaceholder')" />
                <div class="mt-3 flex justify-end">
                    <AppButton variant="accent" size="sm" :disabled="savingReflection" @click="storeReflection">
                        {{ savingReflection ? copy('saving') : copy('reflectionSave') }}
                    </AppButton>
                </div>
            </section>
        </template>
    </div>
</template>
