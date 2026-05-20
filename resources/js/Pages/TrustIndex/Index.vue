<script setup>
import { computed, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from '@/strings/useI18n';

const { t } = useI18n();

const props = defineProps({
    scams: { type: Array, default: () => [] },
    listedCount: { type: Number, default: 0 },
    flash: {
        type: Object,
        default: () => ({ message: null, variant: 'success' }),
    },
});

const query = ref('');
const lastSearchedQuery = ref('');
const searching = ref(false);
const searchError = ref('');
const hasSearched = ref(false);
const result = ref(null);
const flashBanner = ref(props.flash?.message ?? null);
const flashVariant = ref(props.flash?.variant ?? 'success');

watch(
    () => props.flash?.message,
    (message) => {
        if (message) {
            flashBanner.value = message;
            flashVariant.value = props.flash?.variant ?? 'success';
        }
    },
);

watch(query, (value) => {
    if (!hasSearched.value) {
        return;
    }
    if (value.trim() !== lastSearchedQuery.value) {
        hasSearched.value = false;
        result.value = null;
        lastSearchedQuery.value = '';
        searchError.value = '';
    }
});

const scamsByGroup = computed(() => {
    const groups = {};
    for (const scam of props.scams ?? []) {
        const key = scam.group ?? 'other';
        if (!groups[key]) {
            groups[key] = {
                key,
                label: t(`trustIndex.scamGroups.${key}`),
                scams: [],
            };
        }
        groups[key].scams.push(scam);
    }
    return Object.values(groups);
});

function headUrl(username) {
    if (!username) return null;
    return `https://mc-heads.net/avatar/${encodeURIComponent(username)}/64`;
}

function bodyUrl(username) {
    if (!username) return null;
    return `https://mc-heads.net/body/${encodeURIComponent(username)}/128`;
}

function formatDate(iso) {
    if (!iso) return '—';
    try {
        return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(iso));
    } catch {
        return iso;
    }
}

function severityClass(level) {
    const map = {
        LOW: 'trust-severity--low',
        MEDIUM: 'trust-severity--medium',
        HIGH: 'trust-severity--high',
        CRITICAL: 'trust-severity--critical',
    };
    return map[level] ?? 'trust-severity--medium';
}

async function runSearch() {
    const q = query.value.trim();
    searchError.value = '';
    result.value = null;
    hasSearched.value = false;
    lastSearchedQuery.value = '';

    if (q.length < 2) {
        searchError.value = t('trustIndex.searchTooShort');
        return;
    }

    searching.value = true;
    try {
        const response = await fetch(`${route('trust-index.lookup')}?q=${encodeURIComponent(q)}`, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('lookup_failed');
        }

        const data = await response.json();

        if (data.message === 'too_short') {
            searchError.value = t('trustIndex.searchTooShort');
            return;
        }

        lastSearchedQuery.value = q;
        hasSearched.value = true;
        result.value = data.found ? data.scammer : null;
    } catch {
        searchError.value = t('trustIndex.searchError');
    } finally {
        searching.value = false;
    }
}
</script>

<template>
    <Head :title="t('trustIndex.title')" />
    <AuthenticatedLayout>
        <div class="trust-index-page">
            <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
                <p class="guides-eyebrow">{{ t('trustIndex.kicker') }}</p>
                <h1 class="guides-page-title mb-0">{{ t('trustIndex.title') }}</h1>

                <!-- Player lookup (same shell as Profile Stats) -->
                <section class="my-10 sm:my-12" aria-label="Scammer lookup">
                    <div class="w-full max-w-2xl rounded-2xl border border-border/80 bg-surface-900/75 p-3 shadow-[0_16px_40px_rgba(0,0,0,0.35)] backdrop-blur-sm">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <div class="relative flex-1">
                                <svg
                                    class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M8.5 3a5.5 5.5 0 104.35 8.87l2.64 2.64a1 1 0 001.42-1.42l-2.64-2.64A5.5 5.5 0 008.5 3zm-3.5 5.5a3.5 3.5 0 117 0 3.5 3.5 0 01-7 0z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                <input
                                    v-model="query"
                                    type="text"
                                    :placeholder="t('trustIndex.searchPlaceholder')"
                                    class="w-full rounded-xl border border-border/80 bg-surface-800/80 py-3 pl-11 pr-4 text-sm text-white placeholder:text-neutral/80 transition focus:border-profit/70 focus:outline-none focus:ring-2 focus:ring-profit/25"
                                    autocomplete="off"
                                    spellcheck="false"
                                    @keyup.enter="runSearch"
                                />
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-[46px] shrink-0 items-center justify-center rounded-xl border border-profit/35 bg-profit/20 px-6 text-sm font-semibold text-profit transition hover:bg-profit/30 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="searching"
                                @click="runSearch"
                            >
                                {{ searching ? t('trustIndex.searching') : t('trustIndex.search') }}
                            </button>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-neutral/80">
                        {{ t('trustIndex.searchHint', { count: listedCount }) }}
                    </p>
                </section>

                <div
                    v-if="flashBanner"
                    :class="['trust-alert', flashVariant === 'success' ? 'trust-alert--success' : 'trust-alert--error']"
                    role="status"
                >
                    {{ flashBanner }}
                </div>

                <div
                    v-if="searchError"
                    class="trust-alert trust-alert--error"
                    role="alert"
                >
                    {{ searchError }}
                </div>

                <!-- Listed scammer profile -->
                <section
                    v-if="hasSearched && result"
                    class="trust-result trust-result--listed"
                    aria-live="polite"
                >
                    <div class="trust-result-banner">
                        <span class="trust-result-banner-icon" aria-hidden="true">⚠</span>
                        <div>
                            <p class="trust-result-banner-title">{{ t('trustIndex.listedTitle') }}</p>
                            <p class="trust-result-banner-sub">{{ t('trustIndex.listedSub') }}</p>
                        </div>
                    </div>

                    <article class="trust-profile-card">
                        <div class="trust-profile-visual">
                            <img
                                :src="bodyUrl(result.minecraft_username)"
                                :alt="`${result.minecraft_username} skin`"
                                class="trust-profile-body"
                                loading="lazy"
                            />
                        </div>
                        <div class="trust-profile-main">
                            <div class="trust-profile-head-row">
                                <img
                                    :src="headUrl(result.minecraft_username)"
                                    :alt="`${result.minecraft_username} head`"
                                    class="trust-profile-head"
                                    width="48"
                                    height="48"
                                    loading="lazy"
                                />
                                <div>
                                    <h2 class="trust-profile-name">{{ result.minecraft_username }}</h2>
                                    <p v-if="result.player_uuid" class="trust-profile-uuid">
                                        {{ result.player_uuid }}
                                    </p>
                                </div>
                            </div>

                            <div class="trust-profile-badges">
                                <span :class="['trust-badge', severityClass(result.severity_level)]">
                                    {{ result.severity_level }}
                                </span>
                                <span class="trust-badge trust-badge--risk">
                                    {{ t('trustIndex.riskScore') }} {{ result.risk_score }}
                                </span>
                                <span class="trust-badge trust-badge--status">
                                    {{ result.status?.replace(/_/g, ' ') }}
                                </span>
                            </div>

                            <p class="trust-profile-summary">{{ result.summary }}</p>

                            <div class="trust-profile-meta">
                                <span v-if="result.listed_since">
                                    {{ t('trustIndex.listedSince') }} {{ formatDate(result.listed_since) }}
                                </span>
                                <span v-if="result.aliases?.length">
                                    {{ t('trustIndex.aliases') }}: {{ result.aliases.join(', ') }}
                                </span>
                            </div>

                            <Link
                                :href="route('profile-stats', { username: result.minecraft_username })"
                                class="guides-action-btn guides-action-btn--subtle trust-profile-link"
                            >
                                {{ t('trustIndex.viewProfileStats') }} →
                            </Link>
                        </div>
                    </article>

                    <section class="trust-reports">
                        <h3 class="guides-group-title">{{ t('trustIndex.reportsHeading') }}</h3>
                        <ul class="guides-card-list">
                            <li v-for="report in result.reports" :key="report.report_id">
                                <div class="guides-card trust-report-card">
                                    <div class="trust-report-top">
                                        <span class="trust-report-id">{{ report.report_id }}</span>
                                        <span class="trust-report-category">{{ report.category_label }}</span>
                                    </div>
                                    <p class="guides-card-desc trust-report-desc">{{ report.description }}</p>
                                    <p class="trust-report-meta">
                                        {{ t('trustIndex.reported') }} {{ formatDate(report.date_reported) }}
                                        <template v-if="report.items_involved?.length">
                                            · {{ report.items_involved.join(', ') }}
                                        </template>
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </section>
                </section>

                <!-- Not listed -->
                <section
                    v-else-if="hasSearched && !result"
                    class="trust-result trust-result--clear"
                    aria-live="polite"
                >
                    <div class="trust-clear-card">
                        <img
                            v-if="headUrl(lastSearchedQuery)"
                            :src="headUrl(lastSearchedQuery)"
                            :alt="`${lastSearchedQuery} head`"
                            class="trust-clear-head"
                            width="40"
                            height="40"
                            loading="lazy"
                        />
                        <div>
                            <p class="trust-clear-title">{{ t('trustIndex.notListedTitle', { name: lastSearchedQuery }) }}</p>
                            <p class="trust-clear-sub">{{ t('trustIndex.notListedSub') }}</p>
                        </div>
                    </div>
                </section>

                <div v-if="hasSearched" class="trust-cta-row mb-10">
                    <Link
                        :href="route('trust-index.report')"
                        class="guides-action-btn guides-action-btn--primary"
                    >
                        {{ t('trustIndex.ctaReport') }}
                    </Link>
                    <Link
                        v-if="result"
                        :href="route('trust-index.appeal', { username: result.minecraft_username })"
                        class="guides-action-btn guides-action-btn--subtle"
                    >
                        {{ t('trustIndex.ctaAppeal') }}
                    </Link>
                </div>

                <!-- Scam watchlist -->
                <section class="trust-scams-section">
                    <h2 class="trust-scams-heading">{{ t('trustIndex.scamsHeading') }}</h2>
                    <p class="guides-start-sub trust-scams-sub">{{ t('trustIndex.scamsSubheading') }}</p>

                    <div
                        v-for="group in scamsByGroup"
                        :key="group.key"
                        class="guides-topic-group"
                    >
                        <h3 class="trust-scam-group-label">{{ group.label }}</h3>
                        <ul class="guides-card-list">
                            <li v-for="scam in group.scams" :key="scam.id">
                                <div class="guides-card trust-scam-card">
                                    <span class="guides-card-name">
                                        <span class="trust-scam-number">{{ scam.number }}</span>
                                        {{ scam.title }}
                                    </span>
                                    <span class="guides-card-desc">{{ scam.description }}</span>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <p class="trust-disclaimer">{{ t('trustIndex.disclaimer') }}</p>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.trust-index-page {
    min-height: 60vh;
}

.trust-alert {
    margin-bottom: 1.25rem;
    border-radius: 0.75rem;
    padding: 0.75rem 1rem;
    font-size: 0.8125rem;
}

.trust-alert--error {
    border: 1px solid rgba(239, 68, 68, 0.4);
    background: rgba(239, 68, 68, 0.1);
    color: #fca5a5;
}

.trust-alert--success {
    border: 1px solid rgba(11, 202, 81, 0.35);
    background: rgba(11, 202, 81, 0.1);
    color: #bbf7d0;
}

.trust-result {
    margin-bottom: 1.25rem;
}

.trust-cta-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.65rem;
}

.trust-cta-row .guides-action-btn {
    text-decoration: none;
}

.trust-result-banner {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    margin-bottom: 1rem;
    padding: 0.85rem 1rem;
    border-radius: 0.95rem;
    border: 1px solid rgba(239, 68, 68, 0.35);
    background: rgba(127, 29, 29, 0.25);
}

.trust-result-banner-icon {
    font-size: 1.25rem;
    line-height: 1;
}

.trust-result-banner-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: #fecaca;
}

.trust-result-banner-sub {
    margin-top: 0.2rem;
    font-size: 0.8125rem;
    color: rgba(254, 202, 202, 0.75);
}

.trust-profile-card {
    display: grid;
    gap: 1rem;
    padding: 1rem;
    border-radius: 1rem;
    border: 1px solid rgba(239, 68, 68, 0.2);
    background: rgba(18, 18, 20, 0.74);
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.28);
}

@media (min-width: 640px) {
    .trust-profile-card {
        grid-template-columns: auto 1fr;
        align-items: start;
    }
}

.trust-profile-visual {
    display: flex;
    justify-content: center;
}

.trust-profile-body {
    max-height: 10rem;
    width: auto;
    image-rendering: pixelated;
}

.trust-profile-head-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.trust-profile-head {
    border-radius: 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.12);
    image-rendering: pixelated;
}

.trust-profile-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: #fff;
}

.trust-profile-uuid {
    margin-top: 0.15rem;
    font-family: ui-monospace, monospace;
    font-size: 0.6875rem;
    color: rgba(255, 255, 255, 0.35);
    word-break: break-all;
}

.trust-profile-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.75rem;
}

.trust-badge {
    border-radius: 0.35rem;
    padding: 0.15rem 0.45rem;
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.trust-severity--critical {
    background: rgba(239, 68, 68, 0.2);
    color: #fca5a5;
}

.trust-severity--high {
    background: rgba(249, 115, 22, 0.2);
    color: #fdba74;
}

.trust-severity--medium {
    background: rgba(245, 158, 11, 0.2);
    color: #fcd34d;
}

.trust-severity--low {
    background: rgba(16, 185, 129, 0.15);
    color: #6ee7b7;
}

.trust-badge--risk {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.65);
}

.trust-badge--status {
    background: rgba(99, 102, 241, 0.15);
    color: #a5b4fc;
}

.trust-profile-summary {
    margin-top: 0.75rem;
    font-size: 0.875rem;
    line-height: 1.55;
    color: rgba(255, 255, 255, 0.6);
}

.trust-profile-meta {
    margin-top: 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.38);
}

.trust-profile-link {
    display: inline-flex;
    margin-top: 0.85rem;
    width: fit-content;
    text-decoration: none;
}

.trust-reports {
    margin-top: 1.25rem;
}

.trust-report-card {
    padding-left: 1rem;
}

.trust-report-top {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
}

.trust-report-id {
    font-family: ui-monospace, monospace;
    font-size: 0.6875rem;
    color: rgba(255, 255, 255, 0.35);
}

.trust-report-category {
    font-size: 0.6875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: rgba(252, 165, 165, 0.9);
}

.trust-report-desc {
    margin-top: 0;
}

.trust-report-meta {
    margin-top: 0.35rem;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.35);
}

.trust-clear-card {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 1rem;
    border-radius: 0.95rem;
    border: 1px solid rgba(11, 202, 81, 0.25);
    background: rgba(11, 202, 81, 0.08);
}

.trust-clear-head {
    border-radius: 0.4rem;
    image-rendering: pixelated;
}

.trust-clear-title {
    font-size: 0.9375rem;
    font-weight: 600;
    color: #bbf7d0;
}

.trust-clear-sub {
    margin-top: 0.25rem;
    font-size: 0.8125rem;
    color: rgba(187, 247, 208, 0.7);
}

.trust-scams-section {
    margin-top: 3rem;
    padding-top: 2.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.trust-scams-heading {
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1.2;
    color: #fff;
    margin-bottom: 0.5rem;
}

@media (min-width: 640px) {
    .trust-scams-heading {
        font-size: 2rem;
    }
}

.trust-scams-sub {
    margin-bottom: 2rem;
}

.trust-scam-group-label {
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.45);
    margin-bottom: 0.5rem;
}

.trust-scam-card .guides-card-name {
    display: block;
}

.trust-scam-number {
    margin-right: 0.45rem;
    font-variant-numeric: tabular-nums;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.38);
}

.trust-disclaimer {
    margin-top: 2rem;
    font-size: 0.75rem;
    line-height: 1.5;
    color: rgba(255, 255, 255, 0.3);
}
</style>
