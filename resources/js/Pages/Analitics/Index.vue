<script setup>
import AdminOperationsPanel from '@/Components/Admin/AdminOperationsPanel.vue';
import GuildCrawlPanel from '@/Components/Admin/GuildCrawlPanel.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import {
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Tooltip,
    Legend,
    Filler,
);

const props = defineProps({
    rangeDays: { type: Number, default: 30 },
    owner: { type: String, default: 'growth' },
    kpis: { type: Object, default: () => ({}) },
    eventCounts: { type: Object, default: () => ({}) },
    dailyLabels: { type: Array, default: () => [] },
    dailySeries: { type: Object, default: () => ({}) },
    onboardingExperimentVariants: { type: Array, default: () => [] },
    aiSummary: { type: Object, default: () => ({}) },
    operations: { type: Object, default: () => ({}) },
    guildCrawl: { type: Object, default: () => ({}) },
});

const dayOptions = [7, 30, 90];

const eventOrder = [
    'onboarding_view',
    'onboarding_step_complete',
    'onboarding_dismiss',
];

const eventTitles = {
    onboarding_view: 'Onboarding view',
    onboarding_step_complete: 'Step complete',
    onboarding_dismiss: 'Dismiss',
};

const eventColors = {
    onboarding_view: '#A78BFA',
    onboarding_step_complete: '#34D399',
    onboarding_dismiss: '#F59E0B',
};

const numberFmt = (value) => new Intl.NumberFormat('en-US').format(Number(value || 0));
const percentFmt = (value) => `${Number(value || 0).toFixed(1)}%`;
const safeRate = (value) => (value === null || value === undefined ? 'n/a' : percentFmt(value));

const onboardingWinner = computed(() => {
    if (!props.onboardingExperimentVariants?.length) return null;

    return [...props.onboardingExperimentVariants]
        .filter((row) => row.completion_rate_pct !== null)
        .sort((left, right) => Number(right.completion_rate_pct || 0) - Number(left.completion_rate_pct || 0))[0] ?? null;
});

function setRange(days) {
    if (days === props.rangeDays) return;

    router.get(route('admin.index'), { days }, {
        preserveScroll: true,
        preserveState: true,
    });
}

const volumeChart = computed(() => {
    const labels = props.dailyLabels;

    return {
        data: {
            labels,
            datasets: eventOrder.map((eventName) => ({
                label: eventTitles[eventName],
                data: props.dailySeries?.[eventName] || labels.map(() => 0),
                borderColor: eventColors[eventName],
                backgroundColor: `${eventColors[eventName]}22`,
                pointRadius: 0,
                borderWidth: 2,
                tension: 0.25,
                fill: false,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    labels: { color: '#d4d8e3', boxWidth: 10, usePointStyle: true },
                },
            },
            scales: {
                x: {
                    ticks: { color: '#9ca3af', maxTicksLimit: 8 },
                    grid: { color: 'rgba(148, 163, 184, 0.12)' },
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: '#9ca3af' },
                    grid: { color: 'rgba(148, 163, 184, 0.12)' },
                },
            },
        },
    };
});
</script>

<template>
    <Head title="Admin" />

    <AuthenticatedLayout>
        <div class="min-h-screen px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <section class="hero-card mb-6">
                    <div>
                        <p class="hero-kicker">Admin</p>
                        <h1 class="hero-title">Operations &amp; product</h1>
                        <p class="hero-copy">
                            Hypixel API, profile ingest, guild crawl, leaderboard coverage, and onboarding metrics.
                        </p>
                        <p class="mt-3 text-xs uppercase tracking-[0.18em] text-white/45">Owner: {{ owner }}</p>
                    </div>
                    <div class="range-switcher">
                        <button
                            v-for="days in dayOptions"
                            :key="days"
                            type="button"
                            class="range-btn"
                            :class="{ 'range-btn--active': rangeDays === days }"
                            @click="setRange(days)"
                        >
                            {{ days }}d
                        </button>
                    </div>
                </section>

                <AdminOperationsPanel :operations="operations" />

                <GuildCrawlPanel :initial="guildCrawl" />

                <section class="mt-6">
                    <header class="admin-section-head">
                        <h2 class="admin-section-title">Onboarding analytics</h2>
                        <p class="admin-section-sub">Funnel events in the selected period ({{ rangeDays }}d)</p>
                    </header>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <article class="kpi-card">
                            <p class="kpi-label">Onboarding events</p>
                            <p class="kpi-value">{{ numberFmt(kpis.totalEvents) }}</p>
                        </article>
                        <article class="kpi-card">
                            <p class="kpi-label">Unique users</p>
                            <p class="kpi-value">{{ numberFmt(kpis.uniqueUsers) }}</p>
                        </article>
                        <article class="kpi-card">
                            <p class="kpi-label">Onboarding views</p>
                            <p class="kpi-value">{{ numberFmt(eventCounts?.onboarding_view || 0) }}</p>
                        </article>
                        <article class="kpi-card">
                            <p class="kpi-label">Completion rate</p>
                            <p class="kpi-value">{{ safeRate(kpis.onboardingCompletionRatePct) }}</p>
                        </article>
                    </div>
                </section>

                <section class="mt-6">
                    <article class="panel-card">
                        <header class="panel-head">
                            <h2>Onboarding event trend</h2>
                            <p>Daily views, completions, and dismissals</p>
                        </header>
                        <div class="chart-lg">
                            <Line :data="volumeChart.data" :options="volumeChart.options" />
                        </div>
                    </article>
                </section>

                <section class="mt-6">
                    <article class="panel-card">
                        <header class="panel-head">
                            <h2>Onboarding copy performance</h2>
                            <p>View, completion and dismiss signals split by copy variant</p>
                        </header>

                        <div class="ab-summary">
                            <div class="review-card">
                                <p class="review-label">Winner</p>
                                <p class="review-value">{{ onboardingWinner?.variant ? `Variant ${onboardingWinner.variant.toUpperCase()}` : 'n/a' }}</p>
                                <p class="review-delta">Based on completion rate</p>
                            </div>
                            <div class="review-card">
                                <p class="review-label">Best Completion Rate</p>
                                <p class="review-value">{{ safeRate(onboardingWinner?.completion_rate_pct) }}</p>
                                <p class="review-delta">{{ numberFmt(onboardingWinner?.step_completions || 0) }} completions / {{ numberFmt(onboardingWinner?.views || 0) }} views</p>
                            </div>
                            <div class="review-card">
                                <p class="review-label">Dismiss Rate</p>
                                <p class="review-value">{{ safeRate(onboardingWinner?.dismiss_rate_pct) }}</p>
                                <p class="review-delta">{{ numberFmt(onboardingWinner?.dismissals || 0) }} dismissals / {{ numberFmt(onboardingWinner?.views || 0) }} views</p>
                            </div>
                        </div>

                        <div class="table-wrap table-wrap--desktop">
                            <table class="analytics-table experiment-table">
                                <thead>
                                    <tr>
                                        <th>Variant</th>
                                        <th>Views</th>
                                        <th>Step Completions</th>
                                        <th>Dismissals</th>
                                        <th>Completion Rate</th>
                                        <th>Dismiss Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in onboardingExperimentVariants" :key="row.variant">
                                        <td class="segment-source">{{ row.variant.toUpperCase() }}</td>
                                        <td>{{ numberFmt(row.views) }}</td>
                                        <td>{{ numberFmt(row.step_completions) }}</td>
                                        <td>{{ numberFmt(row.dismissals) }}</td>
                                        <td>{{ safeRate(row.completion_rate_pct) }}</td>
                                        <td>{{ safeRate(row.dismiss_rate_pct) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mobile-card-grid mobile-card-grid--table">
                            <article v-for="row in onboardingExperimentVariants" :key="`mobile-onboarding-${row.variant}`" class="mobile-data-card">
                                <div class="mobile-data-card__header">
                                    <p class="mobile-data-card__kicker">Variant</p>
                                    <h3 class="mobile-data-card__title">{{ row.variant.toUpperCase() }}</h3>
                                </div>
                                <dl class="mobile-data-card__stats mobile-data-card__stats--wide">
                                    <div>
                                        <dt>Views</dt>
                                        <dd>{{ numberFmt(row.views) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Completions</dt>
                                        <dd>{{ numberFmt(row.step_completions) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Dismissals</dt>
                                        <dd>{{ numberFmt(row.dismissals) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Completion</dt>
                                        <dd>{{ safeRate(row.completion_rate_pct) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Dismiss</dt>
                                        <dd>{{ safeRate(row.dismiss_rate_pct) }}</dd>
                                    </div>
                                </dl>
                            </article>
                        </div>
                    </article>
                </section>

                <section class="mt-6 grid gap-6 lg:grid-cols-2">
                    <article class="panel-card">
                        <header class="panel-head">
                            <h2>Event Counts Snapshot</h2>
                            <p>Raw counts for audit and fast checks</p>
                        </header>
                        <ul class="stat-list">
                            <li v-for="eventName in eventOrder" :key="eventName">
                                <span>{{ eventTitles[eventName] }}</span>
                                <strong>{{ numberFmt(eventCounts?.[eventName] || 0) }}</strong>
                            </li>
                        </ul>
                    </article>

                    <details class="panel-card admin-advanced">
                        <summary class="admin-advanced__summary">
                            <span>Advanced: AI summary JSON</span>
                        </summary>
                        <pre class="ai-json">{{ JSON.stringify(aiSummary, null, 2) }}</pre>
                    </details>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.hero-card {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.88), rgba(30, 41, 59, 0.9));
    border-radius: 18px;
    padding: 1.25rem;
}

.hero-kicker {
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.18em;
    font-size: 0.68rem;
    color: rgba(148, 163, 184, 0.9);
    font-weight: 700;
}

.hero-title {
    margin: 0.35rem 0 0;
    color: #f8fafc;
    font-size: clamp(1.35rem, 2.1vw, 2rem);
    line-height: 1.1;
    font-weight: 800;
}

.hero-copy {
    margin: 0.45rem 0 0;
    color: rgba(226, 232, 240, 0.82);
    max-width: 70ch;
    font-size: 0.92rem;
}

.range-switcher {
    display: inline-flex;
    padding: 0.2rem;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.72);
    border: 1px solid rgba(148, 163, 184, 0.22);
}

.range-btn {
    border: 0;
    background: transparent;
    color: rgba(226, 232, 240, 0.8);
    font-weight: 700;
    font-size: 0.8rem;
    padding: 0.45rem 0.75rem;
    border-radius: 999px;
    cursor: pointer;
}

.range-btn--active {
    background: rgba(37, 99, 235, 0.35);
    color: #f8fafc;
}

.kpi-card,
.panel-card {
    border: 1px solid rgba(148, 163, 184, 0.2);
    background: rgba(15, 23, 42, 0.72);
    border-radius: 16px;
}

.kpi-card {
    padding: 0.95rem;
}

.kpi-label {
    margin: 0;
    font-size: 0.72rem;
    color: rgba(148, 163, 184, 0.86);
    text-transform: uppercase;
    letter-spacing: 0.13em;
    font-weight: 700;
}

.kpi-value {
    margin: 0.4rem 0 0;
    color: #fff;
    font-size: 1.4rem;
    line-height: 1;
    font-weight: 800;
}

.panel-card {
    padding: 1rem;
}

.panel-head h2 {
    margin: 0;
    color: #f8fafc;
    font-size: 1rem;
    font-weight: 700;
}

.panel-head p {
    margin: 0.3rem 0 0;
    color: rgba(148, 163, 184, 0.88);
    font-size: 0.82rem;
}

.chart-lg {
    margin-top: 0.8rem;
    height: 300px;
}

.chart-md {
    margin-top: 0.8rem;
    height: 260px;
}

.review-grid {
    margin-top: 0.85rem;
    display: grid;
    gap: 0.6rem;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
}

.review-card {
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.2);
    background: rgba(15, 23, 42, 0.6);
    padding: 0.65rem 0.7rem;
}

.review-label {
    margin: 0;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgba(148, 163, 184, 0.85);
    font-weight: 700;
}

.review-value {
    margin: 0.35rem 0 0;
    font-size: 1.15rem;
    color: #f8fafc;
    font-weight: 700;
}

.review-delta {
    margin: 0.25rem 0 0;
    font-size: 0.75rem;
    color: rgba(148, 163, 184, 0.9);
}

.review-note {
    margin-top: 0.65rem;
    color: rgba(148, 163, 184, 0.9);
    font-size: 0.82rem;
}

.ab-summary {
    margin-top: 0.85rem;
    display: grid;
    gap: 0.6rem;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}

.table-wrap {
    margin-top: 0.8rem;
    overflow-x: auto;
}

.table-wrap--desktop {
    display: block;
}

.mobile-card-grid {
    display: none;
    margin-top: 0.8rem;
    gap: 0.65rem;
}

.mobile-data-card {
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(15, 23, 42, 0.72);
    padding: 0.85rem;
}

.mobile-data-card__header {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.mobile-data-card__kicker {
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    font-size: 0.62rem;
    font-weight: 700;
    color: rgba(148, 163, 184, 0.82);
}

.mobile-data-card__title {
    margin: 0;
    color: #f8fafc;
    font-size: 0.92rem;
    font-weight: 700;
}

.mobile-data-card__stats {
    margin-top: 0.7rem;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.55rem;
}

.mobile-data-card__stats--wide {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.mobile-data-card__stats dt {
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgba(148, 163, 184, 0.82);
}

.mobile-data-card__stats dd {
    margin: 0.14rem 0 0;
    color: #f8fafc;
    font-size: 0.8rem;
    font-weight: 600;
}

.analytics-table {
    width: 100%;
    border-collapse: collapse;
}

.analytics-table th,
.analytics-table td {
    border-bottom: 1px solid rgba(148, 163, 184, 0.18);
    padding: 0.62rem;
    text-align: left;
    color: rgba(226, 232, 240, 0.92);
    font-size: 0.84rem;
}

.segment-table th,
.segment-table td {
    font-size: 0.78rem;
}

.segment-source {
    font-weight: 700;
    color: #f8fafc;
    text-transform: capitalize;
}

.experiment-table th,
.experiment-table td {
    font-size: 0.8rem;
}

.analytics-table th {
    color: rgba(148, 163, 184, 0.95);
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-size: 0.66rem;
}

.stat-list {
    margin: 0.85rem 0 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 0.5rem;
}

.stat-list li {
    display: flex;
    justify-content: space-between;
    border-bottom: 1px solid rgba(148, 163, 184, 0.18);
    padding-bottom: 0.45rem;
    color: rgba(226, 232, 240, 0.92);
    font-size: 0.84rem;
}

.stat-list strong {
    color: #fff;
    font-weight: 700;
}

.alert-list {
    margin: 0.8rem 0 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 0.6rem;
}

.alert-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
    border-radius: 12px;
    padding: 0.65rem 0.8rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
    background: rgba(15, 23, 42, 0.6);
}

.alert-item--warning {
    border-color: rgba(248, 113, 113, 0.4);
    background: rgba(248, 113, 113, 0.08);
}

.alert-item--ok {
    border-color: rgba(52, 211, 153, 0.3);
    background: rgba(52, 211, 153, 0.08);
}

.alert-title {
    margin: 0;
    color: #f8fafc;
    font-size: 0.84rem;
    font-weight: 700;
}

.alert-message {
    margin: 0.2rem 0 0;
    color: rgba(226, 232, 240, 0.78);
    font-size: 0.78rem;
}

.alert-meta {
    margin: 0.2rem 0 0;
    color: rgba(148, 163, 184, 0.9);
    font-size: 0.72rem;
}

.alert-pill {
    padding: 0.2rem 0.5rem;
    border-radius: 999px;
    text-transform: uppercase;
    font-size: 0.62rem;
    letter-spacing: 0.12em;
    font-weight: 700;
    color: rgba(226, 232, 240, 0.85);
    border: 1px solid rgba(148, 163, 184, 0.3);
}

.ai-json {
    margin-top: 0.8rem;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.2);
    background: rgba(2, 6, 23, 0.72);
    color: #cbd5e1;
    padding: 0.8rem;
    font-size: 0.74rem;
    line-height: 1.45;
    overflow: auto;
    max-height: 340px;
}

.empty-note {
    margin-top: 1rem;
    color: rgba(148, 163, 184, 0.86);
    font-size: 0.85rem;
}

.admin-section-head {
    margin-bottom: 0.85rem;
}

.admin-section-title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: #f8fafc;
}

.admin-section-sub {
    margin: 0.25rem 0 0;
    font-size: 0.8rem;
    color: rgba(148, 163, 184, 0.88);
}

.admin-advanced__summary {
    cursor: pointer;
    list-style: none;
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(226, 232, 240, 0.9);
    padding: 0.25rem 0;
}

.admin-advanced__summary::-webkit-details-marker {
    display: none;
}

@media (max-width: 640px) {
    .table-wrap--desktop {
        display: none;
    }

    .mobile-card-grid {
        display: grid;
    }
}
</style>
