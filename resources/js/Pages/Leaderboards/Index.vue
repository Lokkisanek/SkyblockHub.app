<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from '@/strings/useI18n';

const { t } = useI18n();

/** Same control chrome as dashboard filter triggers / panels (`Dashboard.vue`). */
const LB_PICK_TRIGGER_BASE =
    'lb-dd-trigger flex min-h-[2.75rem] w-full items-center justify-between gap-2 rounded-xl border border-border/80 bg-surface-800/80 px-3 py-2.5 text-left text-sm font-semibold text-white transition hover:border-border';
const LB_PICK_PANEL_CLASS =
    'lb-panel absolute left-0 right-0 z-30 mt-1.5 rounded-2xl border border-border/80 bg-surface-900/75 p-2 shadow-[0_16px_40px_rgba(0,0,0,0.35)] backdrop-blur-sm sm:right-auto';

const page = usePage();

/** Mirrors App\Http\Controllers\Api\LeaderboardController::SORT_DEFINITIONS if props are missing. */
const FALLBACK_SORT_COLUMNS = [
    { key: 'level', label: 'Level', format: 'integer', align: 'right' },
    { key: 'networth', label: 'Networth', format: 'compact', align: 'right' },
    { key: 'non_cosmetic_networth', label: 'Pure Coins', format: 'compact', align: 'right' },
    { key: 'account_age', label: 'Account Age', format: 'age', align: 'right' },
    { key: 'skill_average', label: 'Skill Avg', format: 'decimal', align: 'right' },
    { key: 'weight', label: 'Weight', format: 'integer', align: 'right' },
    { key: 'slayer_total', label: 'Slayer XP', format: 'compact', align: 'right' },
];

const sortColumns = computed(() => {
    const cols = page.props.sortColumns;
    return Array.isArray(cols) && cols.length ? cols : FALLBACK_SORT_COLUMNS;
});

const filterOptions = computed(() => [
    { key: 'all', label: t('leaderboards.filterAllPlayers') },
    { key: 'app_users', label: t('leaderboards.filterAppUsers') },
    { key: 'non_app_users', label: t('leaderboards.filterNonAppUsers') },
]);

const visitsColumn = { key: 'profile_visits', label: 'Visits', format: 'compact', align: 'right' };

const activeSort = ref('level');
const activeDirection = ref('desc');
const activeFilter = ref('all');
const currentPage = ref(1);
const loading = ref(false);
const leaderboardData = ref(null);
let activeRequestController = null;

const playerSearch = ref('');
const playerSearchError = ref(null);
const playerSearching = ref(false);
const highlightUuid = ref(null);
const pendingJump = ref(null);
let highlightTimer = null;

const normalizeUuidKey = (uuid) => String(uuid || '').replace(/-/g, '').toLowerCase();

const isRowSearchHighlight = (row) =>
    Boolean(highlightUuid.value && normalizeUuidKey(row.minecraft_uuid) === highlightUuid.value);

const findPlayerOnLeaderboard = async () => {
    const q = playerSearch.value.trim();
    playerSearchError.value = null;

    if (q.length < 2) {
        playerSearchError.value = t('leaderboards.findPlayerTooShort');
        return;
    }

    playerSearching.value = true;

    try {
        const params = new URLSearchParams({
            q,
            sort: activeSort.value,
            direction: activeDirection.value,
            filter: activeFilter.value,
        });

        const response = await fetch(`/api/v1/leaderboards/lookup?${params.toString()}`);
        const body = await response.json().catch(() => ({}));

        if (!response.ok) {
            const err = body?.data?.error;
            playerSearchError.value =
                err === 'query_too_short' || response.status === 422
                    ? t('leaderboards.findPlayerTooShort')
                    : t('leaderboards.findPlayerError');
            return;
        }

        const data = body?.data;
        if (!data?.found) {
            playerSearchError.value = t('leaderboards.findPlayerNotFound');
            return;
        }

        const uuidKey = normalizeUuidKey(data.minecraft_uuid);
        pendingJump.value = { page: data.page, uuid: uuidKey };
        currentPage.value = data.page;
    } catch (error) {
        console.error('Leaderboard player lookup failed:', error);
        playerSearchError.value = t('leaderboards.findPlayerError');
    } finally {
        playerSearching.value = false;
    }
};

watch(
    () => [leaderboardData.value, loading.value, pendingJump.value],
    async () => {
        const pj = pendingJump.value;
        if (!pj || loading.value) {
            return;
        }

        const pag = leaderboardData.value?.pagination;
        if (!pag || pag.current_page !== pj.page) {
            return;
        }

        const onPage = leaderboardData.value?.rows?.some(
            (row) => normalizeUuidKey(row.minecraft_uuid) === pj.uuid
        );

        if (!onPage) {
            return;
        }

        await nextTick();
        const el = document.querySelector(`[data-lb-uuid="${pj.uuid}"]`);
        el?.scrollIntoView({ block: 'center', behavior: 'smooth' });

        highlightUuid.value = pj.uuid;
        if (highlightTimer) {
            clearTimeout(highlightTimer);
        }
        highlightTimer = setTimeout(() => {
            highlightUuid.value = null;
            highlightTimer = null;
        }, 4000);

        pendingJump.value = null;
    },
    { flush: 'post' }
);

const activeSortLabel = computed(
    () => sortColumns.value.find((column) => column.key === activeSort.value)?.label ?? 'Leaderboards'
);

const activeSortColumn = computed(() =>
    sortColumns.value.find((column) => column.key === activeSort.value) ?? sortColumns.value[0]
);

/** Metrics shown under each row (everything except the active leaderboard sort). */
const secondaryColumns = computed(() => sortColumns.value.filter((column) => column.key !== activeSort.value));

const expandedRowKeys = ref(new Set());

const leaderboardRowKey = (row) => `${normalizeUuidKey(row.minecraft_uuid)}-${row.rank}`;

const isStatsExpanded = (row) => expandedRowKeys.value.has(leaderboardRowKey(row));

const toggleStatsExpanded = (row) => {
    const key = leaderboardRowKey(row);
    const next = new Set(expandedRowKeys.value);
    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }
    expandedRowKeys.value = next;
};

/** Hypixel-style accent colors for stat values (Tailwind `text-rarity-*`). */
const metricValueClass = (key) => {
    const map = {
        level: 'text-rarity-legendary',
        networth: 'text-rarity-legendary',
        non_cosmetic_networth: 'text-rarity-mythic',
        skill_average: 'text-rarity-divine',
        weight: 'text-rarity-epic',
        slayer_total: 'text-loss',
        account_age: 'text-rarity-common',
        profile_visits: 'text-rarity-uncommon',
    };

    return map[key] ?? 'text-text-primary';
};

const getLeaderboardAvatarUrl = (row) => {
    const uuid = String(row?.minecraft_uuid || row?.linked_minecraft_uuid || '').replace(/-/g, '');

    if (uuid) {
        return `https://mc-heads.net/avatar/${uuid}/128`;
    }

    const fallbackName = row?.profile_username || row?.display_name || 'Steve';
    return `https://mc-heads.net/avatar/${encodeURIComponent(fallbackName)}/128`;
};

const fetchLeaderboard = async () => {
    if (activeRequestController) {
        activeRequestController.abort();
    }

    const controller = new AbortController();
    activeRequestController = controller;
    loading.value = true;

    try {
        const response = await fetch(
            `/api/v1/leaderboards?sort=${encodeURIComponent(activeSort.value)}&direction=${encodeURIComponent(activeDirection.value)}&filter=${encodeURIComponent(activeFilter.value)}&page=${encodeURIComponent(currentPage.value)}`,
            { signal: controller.signal }
        );

        if (!response.ok) {
            throw new Error(`Leaderboard request failed with status ${response.status}`);
        }

        const result = await response.json();
        if (!controller.signal.aborted) {
            leaderboardData.value = result.data;
        }
    } catch (error) {
        if (error?.name !== 'AbortError') {
            console.error('Failed to fetch leaderboard:', error);
        }
    } finally {
        if (activeRequestController === controller) {
            loading.value = false;
            activeRequestController = null;
        }
    }
};

const formatCompactNumber = (value) => {
    const number = Number(value || 0);

    if (!number) {
        return '0';
    }

    if (number >= 1e9) return `${(number / 1e9).toFixed(1)}B`;
    if (number >= 1e6) return `${(number / 1e6).toFixed(1)}M`;
    if (number >= 1e3) return `${(number / 1e3).toFixed(1)}K`;

    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(number);
};

const formatInteger = (value) =>
    new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Number(value || 0));

const formatDecimal = (value) =>
    new Intl.NumberFormat('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(
        Number(value || 0)
    );

const formatAccountAge = (days) => {
    const totalDays = Number(days || 0);

    if (totalDays <= 0) {
        return '—';
    }

    const years = Math.floor(totalDays / 365);
    const months = Math.floor((totalDays % 365) / 30);
    const remainingDays = totalDays % 30;

    if (years > 0) {
        return months > 0 ? `${years}y ${months}mo` : `${years}y`;
    }

    if (months > 0) {
        return `${months}mo ${remainingDays}d`;
    }

    return `${totalDays}d`;
};

const formatDisplayName = (name) => String(name || '').trim();

const formatLastSeen = (timestampMs) => {
    const value = Number(timestampMs || 0);

    if (!value) {
        return '—';
    }

    const diffMs = Date.now() - value;
    if (diffMs < 60000) return 'Just now';

    const minutes = Math.floor(diffMs / 60000);
    if (minutes < 60) return `${minutes}m ago`;

    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;

    const days = Math.floor(hours / 24);
    if (days < 30) return `${days}d ago`;

    const months = Math.floor(days / 30);
    return `${months}mo ago`;
};

const formatStatusTooltip = (row) => {
    if (row.online) {
        return 'Online';
    }

    return `Offline · Last seen ${formatLastSeen(row.last_seen_ts)}`;
};

const getMetricRaw = (row, key) => {
    switch (key) {
        case 'level':
            return row.skyblock_level;
        case 'networth':
            return row.networth;
        case 'non_cosmetic_networth':
            return row.non_cosmetic_networth;
        case 'account_age':
            return row.account_age_days;
        case 'skill_average':
            return row.skill_average;
        case 'weight':
            return row.weight;
        case 'slayer_total':
            return row.slayer_total;
        case 'profile_visits':
            return row.profile_visits;
        default:
            return 0;
    }
};

const getColumnValue = (row, column) => {
    if (!column?.key) {
        return '—';
    }

    const raw = getMetricRaw(row, column.key);

    switch (column.format) {
        case 'integer':
            return formatInteger(raw);
        case 'compact':
            return formatCompactNumber(raw);
        case 'decimal':
            return formatDecimal(raw);
        case 'age':
            return formatAccountAge(raw);
        default:
            return '—';
    }
};

const getStatusLabel = (row) => {
    if (row.online) {
        return 'Online';
    }

    return `Last seen ${formatLastSeen(row.last_seen_ts)}`;
};

const getActionUsername = (row) => row.profile_username || row.display_name;

const getRowPrimaryName = (row) => {
    const d = formatDisplayName(row.display_name || '');
    if (d) {
        return d;
    }

    return formatDisplayName(row.profile_username || row.minecraft_uuid || '');
};

const getRowSubtitleName = (row) => {
    const d = formatDisplayName(row.display_name || '');
    const p = formatDisplayName(row.profile_username || '');
    if (d && p && d !== p) {
        return p;
    }

    return '';
};

/** Digits in rank (without #); drives font + column width so rank never overlaps the avatar. */
const leaderboardRankDigitCount = (rank) => {
    const n = Number(rank);
    if (!Number.isFinite(n) || n < 1) {
        return 1;
    }
    return String(Math.floor(n)).length;
};

/** Tailwind classes for the rank cell to the left of the head. */
const leaderboardRankCellClass = (rank) => {
    const d = leaderboardRankDigitCount(rank);
    const base =
        'shrink-0 select-none pt-0.5 text-right font-black tabular-nums tracking-tight text-neutral-400/90 leading-none';
    if (d <= 1) {
        return `${base} w-12 text-4xl sm:w-14 sm:text-5xl`;
    }
    if (d === 2) {
        return `${base} w-[3.75rem] text-3xl sm:w-16 sm:text-4xl`;
    }
    if (d === 3) {
        return `${base} w-16 text-2xl sm:w-[4.25rem] sm:text-3xl`;
    }
    if (d === 4) {
        return `${base} w-[4.25rem] text-xl sm:w-24 sm:text-2xl`;
    }
    return `${base} min-w-[4.5rem] max-w-[6rem] text-lg sm:min-w-[5.5rem] sm:max-w-[7rem] sm:text-xl`;
};

/** Personal rank headline — same idea, smaller scale in the summary card. */
const leaderboardPersonalRankClass = (rank) => {
    const d = leaderboardRankDigitCount(rank);
    if (d <= 1) {
        return 'text-3xl';
    }
    if (d === 2) {
        return 'text-2xl';
    }
    if (d === 3) {
        return 'text-xl';
    }
    if (d === 4) {
        return 'text-lg';
    }
    return 'text-base';
};

const setSortMetric = (key) => {
    if (activeSort.value !== key) {
        activeSort.value = key;
        currentPage.value = 1;
    }
    closeControlMenus();
};

const setSortDirection = (dir) => {
    if (activeDirection.value !== dir) {
        activeDirection.value = dir;
        currentPage.value = 1;
    }
    closeControlMenus();
};

const setFilter = (key) => {
    activeFilter.value = key;
    currentPage.value = 1;
    closeControlMenus();
};

/** Which leaderboard control dropdown is open ('filter' | 'sort' | 'direction' | null). */
const openControlMenu = ref(null);
const lbControlsRoot = ref(null);

const toggleControlMenu = (key) => {
    openControlMenu.value = openControlMenu.value === key ? null : key;
};

const closeControlMenus = () => {
    openControlMenu.value = null;
};

const onDocumentPointerDown = (event) => {
    const root = lbControlsRoot.value;
    if (root instanceof HTMLElement && !root.contains(event.target)) {
        closeControlMenus();
    }
};

const activeFilterLabel = computed(() => filterOptions.value.find((o) => o.key === activeFilter.value)?.label ?? '');
const activeDirectionLabel = computed(() =>
    activeDirection.value === 'desc' ? t('common.descending') : t('common.ascending')
);

const goToPage = (page) => {
    if (!leaderboardData.value?.pagination) {
        return;
    }

    const lastPage = leaderboardData.value.pagination.last_page || 1;
    currentPage.value = Math.min(Math.max(page, 1), lastPage);
};

watch([activeSort, activeDirection, activeFilter, currentPage], () => {
    fetchLeaderboard();
});

watch([activeSort, activeDirection, activeFilter], () => {
    playerSearchError.value = null;
    expandedRowKeys.value = new Set();
});

watch(currentPage, () => {
    expandedRowKeys.value = new Set();
});

onMounted(() => {
    fetchLeaderboard();
    document.addEventListener('pointerdown', onDocumentPointerDown, true);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown, true);
    if (activeRequestController) {
        activeRequestController.abort();
    }
    if (highlightTimer) {
        clearTimeout(highlightTimer);
        highlightTimer = null;
    }
});
</script>

<template>
    <Head :title="t('leaderboards.title')" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <header class="max-w-2xl space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-text-tertiary">{{ t('leaderboards.kicker') }}</p>
                <h1 class="text-3xl font-bold tracking-tight text-text-primary">{{ t('leaderboards.title') }}</h1>
                <p class="text-sm leading-relaxed text-text-secondary">{{ t('leaderboards.copy') }}</p>
            </header>

            <div class="flex flex-col items-center gap-3">
                <div ref="lbControlsRoot" class="flex w-full max-w-2xl flex-col gap-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div class="relative min-w-0 flex-1">
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
                                id="leaderboard-player-search"
                                v-model="playerSearch"
                                type="text"
                                class="lb-inset w-full border border-border/80 bg-surface-800/80 py-3 pl-11 pr-4 text-sm text-white placeholder:text-neutral/80 transition focus:border-profit/70 focus:outline-none focus:ring-2 focus:ring-profit/25"
                                :placeholder="t('leaderboards.findPlayerPlaceholder')"
                                :aria-label="t('leaderboards.findPlayerPlaceholder')"
                                autocomplete="off"
                                @keydown.enter.prevent="findPlayerOnLeaderboard"
                            />
                        </div>
                        <button
                            type="button"
                            class="lb-accent inline-flex h-[46px] shrink-0 items-center justify-center border border-profit/35 bg-profit/20 px-6 text-sm font-semibold text-profit transition hover:bg-profit/30 hover:text-white disabled:cursor-wait disabled:opacity-50"
                            :disabled="playerSearching"
                            @click="findPlayerOnLeaderboard"
                        >
                            {{ playerSearching ? t('leaderboards.findPlayerSearching') : t('leaderboards.findPlayerGo') }}
                        </button>
                    </div>
                    <p v-if="playerSearchError" class="mt-2 text-xs text-loss" role="alert">{{ playerSearchError }}</p>

                    <div class="flex flex-wrap items-stretch justify-center gap-2 sm:justify-start">
                        <div class="relative min-w-[9.5rem] flex-1 sm:flex-none sm:min-w-[11rem]">
                            <button
                                type="button"
                                :class="[
                                    LB_PICK_TRIGGER_BASE,
                                    openControlMenu === 'filter'
                                        ? 'border-profit/70 text-white ring-2 ring-profit/25'
                                        : 'text-white/90',
                                ]"
                                :aria-expanded="openControlMenu === 'filter'"
                                aria-haspopup="listbox"
                                @click.stop="toggleControlMenu('filter')"
                            >
                                <span class="min-w-0">
                                    <span class="block text-[9px] font-bold uppercase tracking-[0.14em] text-neutral/80">{{ t('leaderboards.controlFilterCaption') }}</span>
                                    <span class="mt-0.5 block truncate text-[13px] font-semibold text-white">{{ activeFilterLabel }}</span>
                                </span>
                                <svg class="h-4 w-4 shrink-0 text-neutral/70" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path
                                        fill-rule="evenodd"
                                        d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                            <div
                                v-show="openControlMenu === 'filter'"
                                :class="[LB_PICK_PANEL_CLASS, 'sm:min-w-[12rem]']"
                                role="listbox"
                                @click.stop
                            >
                                <button
                                    v-for="option in filterOptions"
                                    :key="option.key"
                                    type="button"
                                    role="option"
                                    class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-xs font-medium text-white/80 transition hover:bg-surface-800/90"
                                    :class="activeFilter === option.key ? 'bg-profit/15 text-profit' : ''"
                                    @click="setFilter(option.key)"
                                >
                                    {{ option.label }}
                                    <span v-if="activeFilter === option.key" class="text-profit">✓</span>
                                </button>
                            </div>
                        </div>

                        <div class="relative min-w-[9.5rem] flex-[1.4] sm:flex-none sm:min-w-[13rem]">
                            <button
                                type="button"
                                :class="[
                                    LB_PICK_TRIGGER_BASE,
                                    openControlMenu === 'sort'
                                        ? 'border-profit/70 text-white ring-2 ring-profit/25'
                                        : 'text-white/90',
                                ]"
                                :aria-expanded="openControlMenu === 'sort'"
                                aria-haspopup="listbox"
                                @click.stop="toggleControlMenu('sort')"
                            >
                                <span class="min-w-0">
                                    <span class="block text-[9px] font-bold uppercase tracking-[0.14em] text-neutral/80">{{ t('leaderboards.controlSortCaption') }}</span>
                                    <span class="mt-0.5 block truncate text-[13px] font-semibold text-white">{{ activeSortLabel }}</span>
                                </span>
                                <svg class="h-4 w-4 shrink-0 text-neutral/70" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path
                                        fill-rule="evenodd"
                                        d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                            <div
                                v-show="openControlMenu === 'sort'"
                                :class="[LB_PICK_PANEL_CLASS, 'max-h-64 overflow-y-auto sm:min-w-[14rem]']"
                                role="listbox"
                                @click.stop
                            >
                                <button
                                    v-for="col in sortColumns"
                                    :key="col.key"
                                    type="button"
                                    role="option"
                                    class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-xs font-medium text-white/80 transition hover:bg-surface-800/90"
                                    :class="activeSort === col.key ? 'bg-profit/15 text-profit' : ''"
                                    @click="setSortMetric(col.key)"
                                >
                                    {{ col.label }}
                                    <span v-if="activeSort === col.key" class="text-profit">✓</span>
                                </button>
                            </div>
                        </div>

                        <div class="relative min-w-[9.5rem] flex-1 sm:flex-none sm:min-w-[11rem]">
                            <button
                                type="button"
                                :class="[
                                    LB_PICK_TRIGGER_BASE,
                                    openControlMenu === 'direction'
                                        ? 'border-profit/70 text-white ring-2 ring-profit/25'
                                        : 'text-white/90',
                                ]"
                                :aria-expanded="openControlMenu === 'direction'"
                                aria-haspopup="listbox"
                                @click.stop="toggleControlMenu('direction')"
                            >
                                <span class="min-w-0">
                                    <span class="block text-[9px] font-bold uppercase tracking-[0.14em] text-neutral/80">{{ t('leaderboards.controlDirectionCaption') }}</span>
                                    <span class="mt-0.5 block truncate text-[13px] font-semibold text-white">{{ activeDirectionLabel }}</span>
                                </span>
                                <svg class="h-4 w-4 shrink-0 text-neutral/70" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path
                                        fill-rule="evenodd"
                                        d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                            <div
                                v-show="openControlMenu === 'direction'"
                                :class="[LB_PICK_PANEL_CLASS, 'sm:min-w-[11rem]']"
                                role="listbox"
                                @click.stop
                            >
                                <button
                                    type="button"
                                    role="option"
                                    class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-xs font-medium text-white/80 transition hover:bg-surface-800/90"
                                    :class="activeDirection === 'desc' ? 'bg-profit/15 text-profit' : ''"
                                    @click="setSortDirection('desc')"
                                >
                                    {{ t('common.descending') }}
                                    <span v-if="activeDirection === 'desc'" class="text-profit">✓</span>
                                </button>
                                <button
                                    type="button"
                                    role="option"
                                    class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-xs font-medium text-white/80 transition hover:bg-surface-800/90"
                                    :class="activeDirection === 'asc' ? 'bg-profit/15 text-profit' : ''"
                                    @click="setSortDirection('asc')"
                                >
                                    {{ t('common.ascending') }}
                                    <span v-if="activeDirection === 'asc'" class="text-profit">✓</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="max-w-2xl px-1 text-center text-[11px] leading-relaxed text-text-tertiary sm:text-left">{{ t('leaderboards.metricOrderHint') }}</p>
            </div>

            <section v-if="leaderboardData?.personal" class="mt-1">
                <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-border bg-surface-800/80 px-4 py-4 sm:px-5">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-text-tertiary">{{ t('leaderboards.personalKicker') }}</p>
                        <h2 class="mt-1 text-base font-bold text-text-primary">{{ t('leaderboards.personalTitle') }}</h2>
                        <p class="mt-1 text-xs text-text-secondary">{{ t('leaderboards.personalCopy') }}</p>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-text-tertiary">{{ t('leaderboards.personalRank') }}</span>
                        <div class="mt-0.5 font-black tabular-nums leading-none text-neutral-400/90" :class="leaderboardPersonalRankClass(leaderboardData.personal.rank)">
                            #{{ leaderboardData.personal.rank }}
                        </div>
                    </div>
                </div>
            </section>

            <section class="relative">
                <div
                    v-if="loading"
                    class="flex min-h-[220px] items-center justify-center gap-3 py-8 text-neutral"
                    role="status"
                    aria-live="polite"
                >
                    <span class="h-5 w-5 shrink-0 animate-spin rounded-full border-2 border-neutral/40 border-t-profit" />
                    <span class="text-sm text-neutral">{{ t('leaderboards.tableLoading') }}</span>
                </div>

                <div v-else-if="!leaderboardData?.rows?.length" class="flex min-h-[220px] items-center justify-center rounded-xl border border-border bg-surface-800/50 px-4 py-12 text-center text-sm text-text-secondary">
                    <p>{{ t('leaderboards.emptyLeaderboard') }}</p>
                </div>

                <div v-else class="space-y-3">
                    <article
                        v-for="row in leaderboardData.rows"
                        :key="`${row.user_id ?? row.minecraft_uuid}-${row.rank}`"
                        class="lb-surface border border-border/80 bg-surface-900/75 p-4 shadow-[0_16px_40px_rgba(0,0,0,0.35)] backdrop-blur-sm transition sm:p-5"
                        :class="{
                            'border-primary/40 bg-surface-900/88 ring-1 ring-primary/30': isRowSearchHighlight(row),
                        }"
                        :data-lb-uuid="normalizeUuidKey(row.minecraft_uuid)"
                    >
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
                            <div class="flex min-w-0 gap-3 sm:gap-4">
                                <span :class="leaderboardRankCellClass(row.rank)">
                                    #{{ row.rank }}
                                </span>
                                <img
                                    v-if="row.minecraft_uuid || row.linked_minecraft_uuid"
                                    class="h-11 w-11 shrink-0 rounded-lg border border-border object-cover"
                                    :src="getLeaderboardAvatarUrl(row)"
                                    :alt="`${getRowPrimaryName(row)} Minecraft avatar`"
                                    loading="lazy"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                        <span
                                            v-if="row.hypixel_rank"
                                            class="shrink-0 text-[11px] font-semibold"
                                            :style="{ color: row.hypixel_rank_color || '#94a3b8' }"
                                        >
                                            {{ row.hypixel_rank }}
                                        </span>
                                        <span class="truncate text-base font-semibold text-text-primary sm:text-lg">
                                            {{ getRowPrimaryName(row) }}
                                        </span>
                                        <span
                                            v-if="row.is_app_user"
                                            class="shrink-0 rounded-full border border-border bg-surface-900/80 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-text-tertiary"
                                        >
                                            {{ t('leaderboards.appUserTag') }}
                                        </span>
                                    </div>
                                    <p v-if="getRowSubtitleName(row)" class="mt-0.5 truncate text-xs text-text-secondary">
                                        {{ getRowSubtitleName(row) }}
                                    </p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <span
                                            class="inline-flex items-center gap-1.5 text-xs text-text-secondary"
                                            :title="formatStatusTooltip(row)"
                                        >
                                            <span
                                                class="h-2 w-2 shrink-0 rounded-full"
                                                :class="row.online ? 'bg-emerald-500 shadow-[0_0_0_3px_rgba(16,185,129,0.15)]' : 'bg-slate-500 shadow-[0_0_0_3px_rgba(100,116,139,0.12)]'"
                                            />
                                            {{ getStatusLabel(row) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="shrink-0 border-t border-border pt-3 sm:border-t-0 sm:border-l sm:pt-0 sm:pl-6 sm:text-right">
                                <p
                                    class="text-2xl font-black tabular-nums tracking-tight sm:text-3xl"
                                    :class="metricValueClass(activeSortColumn.key)"
                                >
                                    {{ getColumnValue(row, activeSortColumn) }}
                                </p>
                                <p class="mt-1 text-[10px] font-bold uppercase tracking-[0.14em] text-amber-200/75">{{ activeSortLabel }}</p>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-1.5 border-t border-border pt-3">
                            <button
                                type="button"
                                class="lb-row-btn inline-flex items-center gap-1.5 border border-border bg-surface-700/85 px-3 py-2 text-xs font-medium text-text-primary transition hover:border-primary/40 hover:bg-surface-600/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-profit/30 focus-visible:ring-offset-0"
                                :aria-expanded="isStatsExpanded(row)"
                                @click="toggleStatsExpanded(row)"
                            >
                                <span>{{ isStatsExpanded(row) ? t('leaderboards.collapseStats') : t('leaderboards.expandStats') }}</span>
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    class="h-4 w-4 shrink-0 text-text-secondary transition-transform duration-200"
                                    :class="{ 'rotate-180': isStatsExpanded(row) }"
                                    aria-hidden="true"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                            <Link
                                v-if="getActionUsername(row)"
                                :href="route('profile-stats', { username: getActionUsername(row) })"
                                class="lb-row-btn inline-flex items-center border border-border bg-surface-700/85 px-3 py-2 text-xs font-medium text-text-primary transition hover:border-primary/40 hover:bg-surface-600/90"
                            >
                                {{ t('nav.profileStats') }}
                            </Link>
                            <Link
                                v-if="row.is_app_user && row.has_public_dashboard && row.linked_minecraft_uuid"
                                :href="route('dashboard.visit', { minecraftUuid: row.linked_minecraft_uuid })"
                                class="lb-row-btn inline-flex items-center border border-primary/35 bg-primary/15 px-3 py-2 text-xs font-medium text-primary transition hover:bg-primary/22"
                            >
                                {{ t('nav.dashboard') }}
                            </Link>
                        </div>

                        <div v-show="isStatsExpanded(row)" class="mt-3">
                            <p class="mb-3 text-[10px] font-bold uppercase tracking-[0.16em] text-text-tertiary">
                                {{ t('leaderboards.otherStatsHeading') }}
                            </p>
                            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3 lg:grid-cols-4">
                                <div v-for="col in secondaryColumns" :key="`sub-${col.key}`">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral">{{ col.label }}</dt>
                                    <dd class="mt-0.5 text-sm font-bold tabular-nums" :class="metricValueClass(col.key)">
                                        {{ getColumnValue(row, col) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral">{{ t('leaderboards.visitsLabel') }}</dt>
                                    <dd class="mt-0.5 text-sm font-bold tabular-nums" :class="metricValueClass('profile_visits')">
                                        {{ getColumnValue(row, visitsColumn) }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </article>
                </div>
            </section>

            <section v-if="leaderboardData?.pagination" class="flex flex-wrap items-center justify-center gap-3 pt-1 sm:justify-between">
                <button
                    type="button"
                    class="rounded-lg border border-border bg-surface-800/80 px-4 py-2 text-sm font-medium text-text-primary transition hover:border-primary/40 hover:bg-surface-700/80 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="currentPage === 1"
                    @click="goToPage(currentPage - 1)"
                >
                    {{ t('common.previous') }}
                </button>
                <span class="text-sm text-text-secondary">
                    {{ t('leaderboards.pageOf', { current: leaderboardData.pagination.current_page, last: leaderboardData.pagination.last_page }) }}
                </span>
                <button
                    type="button"
                    class="rounded-lg border border-border bg-surface-800/80 px-4 py-2 text-sm font-medium text-text-primary transition hover:border-primary/40 hover:bg-surface-700/80 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="currentPage === leaderboardData.pagination.last_page"
                    @click="goToPage(currentPage + 1)"
                >
                    {{ t('common.next') }}
                </button>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Smooth “continuous” corners: squircle where supported (G3-like), pill row buttons. */
.lb-surface,
.lb-inset,
.lb-accent,
.lb-dd-trigger,
.lb-panel,
.lb-row-btn {
    border-radius: 1.125rem;
}

@supports (corner-shape: squircle) {
    .lb-surface,
    .lb-inset,
    .lb-accent,
    .lb-dd-trigger,
    .lb-panel {
        corner-shape: squircle;
    }
}

.lb-row-btn {
    border-radius: 0.8125rem;
}

@supports (corner-shape: squircle) {
    .lb-row-btn {
        corner-shape: squircle;
    }
}
</style>
