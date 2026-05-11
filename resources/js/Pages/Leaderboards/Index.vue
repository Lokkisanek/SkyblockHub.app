<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const sortTabs = [
    { key: 'level', label: 'Level' },
    { key: 'networth', label: 'Networth' },
    { key: 'non_cosmetic_networth', label: 'Pure Coins' },
    { key: 'account_age', label: 'Account Age' },
];

const periodOptions = computed(() => [
    { key: 'all', label: t('leaderboards.periodAll') },
    { key: 'daily', label: t('leaderboards.periodDaily') },
    { key: 'weekly', label: t('leaderboards.periodWeekly') },
    { key: 'monthly', label: t('leaderboards.periodMonthly') },
]);

const filterOptions = [
    { key: 'all', label: 'All Players' },
    { key: 'app_users', label: 'App Users Only' },
    { key: 'non_app_users', label: 'Non-App Users' },
];

const sortableColumns = [
    { key: 'level', label: 'Level', format: 'integer', align: 'right' },
    { key: 'networth', label: 'Networth', format: 'compact', align: 'right' },
    { key: 'skill_average', label: 'Skill Avg', format: 'decimal', align: 'right' },
    { key: 'slayer_total', label: 'Slayer XP', format: 'compact', align: 'right' },
    { key: 'account_age', label: 'Age', format: 'age', align: 'right' },
    { key: 'profile_visits', label: 'Visits', format: 'compact', align: 'right' },
];

const activeSort = ref('level');
const activeDirection = ref('desc');
const activeFilter = ref('all');
const activePeriod = ref('all');
const currentPage = ref(1);
const loading = ref(false);
const leaderboardData = ref(null);
let activeRequestController = null;

const activeSortLabel = computed(
    () => sortableColumns.find((column) => column.key === activeSort.value)?.label ?? 'Leaderboards'
);

const podiumRows = computed(() => leaderboardData.value?.rows?.slice(0, 3) ?? []);

const podiumPlacements = computed(() => {
    const [first, second, third] = podiumRows.value;

    return [
        { slot: 'second', row: second },
        { slot: 'first', row: first },
        { slot: 'third', row: third },
    ].filter((entry) => entry.row);
});

const getPodiumAvatarUrl = (row) => {
    const uuid = String(row?.minecraft_uuid || row?.linked_minecraft_uuid || '').replace(/-/g, '');

    if (uuid) {
        return `https://mc-heads.net/avatar/${uuid}/128`;
    }

    const fallbackName = row?.profile_username || row?.display_name || 'Steve';
    return `https://mc-heads.net/avatar/${encodeURIComponent(fallbackName)}/128`;
};

const getPodiumStatLabel = () => activeSortLabel.value;

const getPodiumStatValue = (row) => getColumnValue(row, activeSort.value);

const movementBadge = (row) => {
    if (row?.movement === null || row?.movement === undefined) {
        return null;
    }

    if (row.movement > 0) {
        return { icon: '▲', label: `+${row.movement}` };
    }

    if (row.movement < 0) {
        return { icon: '▼', label: `${row.movement}` };
    }

    return { icon: '•', label: '0' };
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
            `/api/v1/leaderboards?sort=${encodeURIComponent(activeSort.value)}&direction=${encodeURIComponent(activeDirection.value)}&filter=${encodeURIComponent(activeFilter.value)}&period=${encodeURIComponent(activePeriod.value)}&page=${encodeURIComponent(currentPage.value)}`,
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

const getColumnValue = (row, columnKey) => {
    switch (columnKey) {
        case 'level':
            return formatInteger(row.skyblock_level);
        case 'networth':
            return formatCompactNumber(row.networth);
        case 'skill_average':
            return formatDecimal(row.skill_average);
        case 'slayer_total':
            return formatCompactNumber(row.slayer_total);
        case 'account_age':
            return formatAccountAge(row.account_age_days);
        case 'profile_visits':
            return formatCompactNumber(row.profile_visits);
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

const isActiveSort = (key) => activeSort.value === key;

const sortIndicator = (key) => {
    if (!isActiveSort(key)) {
        return '↕';
    }

    return activeDirection.value === 'desc' ? '↓' : '↑';
};

const setSort = (key) => {
    if (activeSort.value === key) {
        activeDirection.value = activeDirection.value === 'desc' ? 'asc' : 'desc';
    } else {
        activeSort.value = key;
        activeDirection.value = 'desc';
    }

    currentPage.value = 1;
};

const setFilter = (key) => {
    activeFilter.value = key;
    currentPage.value = 1;
};

const setPeriod = (key) => {
    activePeriod.value = key;
    currentPage.value = 1;
};

const goToPage = (page) => {
    if (!leaderboardData.value?.pagination) {
        return;
    }

    const lastPage = leaderboardData.value.pagination.last_page || 1;
    currentPage.value = Math.min(Math.max(page, 1), lastPage);
};

watch([activeSort, activeDirection, activeFilter, activePeriod, currentPage], () => {
    fetchLeaderboard();
});

onMounted(() => {
    fetchLeaderboard();
});

onBeforeUnmount(() => {
    if (activeRequestController) {
        activeRequestController.abort();
    }
});
</script>

<template>
    <Head title="Leaderboards" />

    <AuthenticatedLayout>
        <div class="leaderboards-page">
            <div class="leaderboards-shell mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <section class="leaderboards-hero">
                    <div class="mb-5 flex items-center justify-between gap-4">
                        <div>
                            <p class="leaderboards-kicker">{{ t('leaderboards.kicker') }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2.5">
                                <h1 class="leaderboards-title">{{ t('leaderboards.title') }}</h1>
                                <span class="inline-flex items-center rounded-full border border-amber-300/35 bg-amber-300/12 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-amber-100">
                                    {{ t('leaderboards.betaTag') }}
                                </span>
                                <Link
                                    :href="route('leaderboards.info')"
                                    class="inline-flex items-center rounded-full border border-white/15 bg-white/5 px-2.5 py-1 text-[11px] font-medium text-white/75 transition hover:border-white/30 hover:bg-white/10 hover:text-white"
                                >
                                    {{ t('leaderboards.seeMoreInfo') }}
                                </Link>
                            </div>
                        </div>
                    </div>
                    <div class="leaderboards-hero__copy">
                        <p class="leaderboards-copy">{{ t('leaderboards.copy') }}</p>
                    </div>

                    <div class="leaderboards-meta">
                        <div class="meta-card">
                            <span class="meta-card__label">Sorted by</span>
                            <strong class="meta-card__value">{{ activeSortLabel }}</strong>
                        </div>
                        <div class="meta-card">
                            <span class="meta-card__label">Direction</span>
                            <strong class="meta-card__value">{{ activeDirection === 'desc' ? 'Descending' : 'Ascending' }}</strong>
                        </div>
                    </div>
                </section>

                <section class="leaderboards-controls">
                    <div class="chip-row">
                        <button
                            v-for="option in periodOptions"
                            :key="option.key"
                            type="button"
                            :class="['chip', { 'chip--active': activePeriod === option.key }]"
                            @click="setPeriod(option.key)"
                        >
                            {{ option.label }}
                        </button>
                    </div>

                    <div class="chip-row">
                        <button
                            v-for="option in filterOptions"
                            :key="option.key"
                            type="button"
                            :class="['chip', { 'chip--active': activeFilter === option.key }]"
                            @click="setFilter(option.key)"
                        >
                            {{ option.label }}
                        </button>
                    </div>

                    <div class="chip-row chip-row--muted">
                        <button
                            v-for="tab in sortTabs"
                            :key="tab.key"
                            type="button"
                            :class="['chip', 'chip--sort', { 'chip--active': activeSort === tab.key }]"
                            @click="setSort(tab.key)"
                        >
                            <span>{{ tab.label }}</span>
                            <span class="chip__direction">{{ sortIndicator(tab.key) }}</span>
                        </button>
                    </div>
                </section>

                <section v-if="leaderboardData?.personal" class="leaderboards-personal">
                    <div class="personal-card">
                        <div>
                            <p class="personal-kicker">{{ t('leaderboards.personalKicker') }}</p>
                            <h2 class="personal-title">{{ t('leaderboards.personalTitle') }}</h2>
                            <p class="personal-copy">{{ t('leaderboards.personalCopy') }}</p>
                        </div>
                        <div class="personal-rank">
                            <span class="personal-rank__label">{{ t('leaderboards.personalRank') }}</span>
                            <div class="personal-rank__value">
                                #{{ leaderboardData.personal.rank }}
                                <span v-if="movementBadge(leaderboardData.personal)" class="movement-chip" :class="movementBadge(leaderboardData.personal).icon === '▲' ? 'movement-chip--up' : (movementBadge(leaderboardData.personal).icon === '▼' ? 'movement-chip--down' : '')">
                                    {{ movementBadge(leaderboardData.personal).icon }} {{ movementBadge(leaderboardData.personal).label }}
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="podiumPlacements.length" class="leaderboards-podium" aria-label="Top 3 players">
                    <article
                        v-for="entry in podiumPlacements"
                        :key="`podium-${entry.slot}-${entry.row.user_id ?? entry.row.minecraft_uuid}`"
                        class="podium-card"
                        :class="[`podium-card--${entry.slot}`]"
                    >
                        <div class="podium-card__rank">#{{ entry.row.rank }}</div>
                        <img
                            class="podium-card__avatar"
                            :src="getPodiumAvatarUrl(entry.row)"
                            :alt="`${formatDisplayName(entry.row.display_name || entry.row.profile_username || entry.row.minecraft_uuid)} Minecraft avatar`"
                            loading="lazy"
                        />
                        <div class="podium-card__name">
                            <span v-if="entry.row.hypixel_rank" class="player-rank" :style="{ color: entry.row.hypixel_rank_color || '#94a3b8' }">
                                {{ entry.row.hypixel_rank }}
                            </span>
                            <strong>{{ formatDisplayName(entry.row.display_name || entry.row.profile_username || entry.row.minecraft_uuid) }}</strong>
                        </div>
                        <div class="podium-card__stat-label">{{ getPodiumStatLabel() }}</div>
                        <div class="podium-card__stat-value">{{ getPodiumStatValue(entry.row) }}</div>
                        <div v-if="movementBadge(entry.row)" class="movement-chip" :class="movementBadge(entry.row).icon === '▲' ? 'movement-chip--up' : (movementBadge(entry.row).icon === '▼' ? 'movement-chip--down' : '')">
                            {{ movementBadge(entry.row).icon }} {{ movementBadge(entry.row).label }}
                        </div>
                    </article>
                </section>

                <section class="leaderboards-table-shell">
                    <div v-if="loading" class="leaderboards-loading" aria-live="polite">
                        <span class="loading-bar"></span>
                        <span class="loading-text">Refreshing leaderboard</span>
                    </div>

                    <div v-else-if="!leaderboardData?.rows?.length" class="leaderboards-empty">
                        <p>No players found for the selected filters.</p>
                    </div>

                    <div v-else class="leaderboards-results">
                        <div class="leaderboards-table-wrap leaderboards-table-wrap--desktop">
                            <table class="leaderboards-table">
                                <thead>
                                    <tr>
                                        <th class="col-rank">#</th>
                                        <th class="col-movement">Δ</th>
                                        <th class="col-player">Player</th>
                                        <th
                                            v-for="column in sortableColumns"
                                            :key="column.key"
                                            :class="['col-data', `col-${column.key}`, `align-${column.align}`]"
                                            :aria-sort="isActiveSort(column.key) ? (activeDirection === 'desc' ? 'descending' : 'ascending') : 'none'"
                                        >
                                            <button type="button" class="th-button" @click="setSort(column.key)">
                                                <span>{{ column.label }}</span>
                                                <span class="th-button__sort" :class="{ 'th-button__sort--active': isActiveSort(column.key) }">
                                                    {{ sortIndicator(column.key) }}
                                                </span>
                                            </button>
                                        </th>
                                        <th class="col-status">Status</th>
                                        <th class="col-actions">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in leaderboardData.rows" :key="`${row.user_id ?? row.minecraft_uuid}-${row.rank}`" class="leaderboard-row">
                                        <td class="col-rank">
                                            <span class="rank-badge">{{ row.rank }}</span>
                                        </td>
                                        <td class="col-movement">
                                            <span v-if="movementBadge(row)" class="movement-chip" :class="movementBadge(row).icon === '▲' ? 'movement-chip--up' : (movementBadge(row).icon === '▼' ? 'movement-chip--down' : '')">
                                                {{ movementBadge(row).icon }} {{ movementBadge(row).label }}
                                            </span>
                                            <span v-else class="movement-chip movement-chip--neutral">—</span>
                                        </td>
                                        <td class="col-player">
                                            <div class="player-cell">
                                                <img
                                                    v-if="row.minecraft_uuid || row.linked_minecraft_uuid"
                                                    class="player-avatar"
                                                    :src="getPodiumAvatarUrl(row)"
                                                    :alt="`${formatDisplayName(row.display_name || row.profile_username || row.minecraft_uuid)} Minecraft avatar`"
                                                    loading="lazy"
                                                />
                                                <span
                                                    v-if="row.hypixel_rank"
                                                    class="player-rank"
                                                    :style="{ color: row.hypixel_rank_color || '#94a3b8' }"
                                                >
                                                    {{ row.hypixel_rank }}
                                                </span>
                                                <div class="player-name-wrap">
                                                    <span class="player-name">{{ formatDisplayName(row.display_name || row.profile_username || row.minecraft_uuid) }}</span>
                                                    <span v-if="row.is_app_user" class="player-flag">App user</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="col-data col-level align-right">{{ getColumnValue(row, 'level') }}</td>
                                        <td class="col-data col-networth align-right">{{ getColumnValue(row, 'networth') }}</td>
                                        <td class="col-data col-skill_average align-right">{{ getColumnValue(row, 'skill_average') }}</td>
                                        <td class="col-data col-slayer_total align-right">{{ getColumnValue(row, 'slayer_total') }}</td>
                                        <td class="col-data col-account_age align-right">{{ getColumnValue(row, 'account_age') }}</td>
                                        <td class="col-data col-profile_visits align-right">{{ getColumnValue(row, 'profile_visits') }}</td>
                                        <td class="col-status">
                                            <span
                                                class="status-text"
                                                :class="row.online ? 'status-text--online' : 'status-text--offline'"
                                                :title="formatStatusTooltip(row)"
                                                :aria-label="formatStatusTooltip(row)"
                                            >
                                                {{ getStatusLabel(row) }}
                                            </span>
                                        </td>
                                        <td class="col-actions">
                                            <div class="row-actions">
                                                <Link
                                                    v-if="getActionUsername(row)"
                                                    :href="route('profile-stats', { username: getActionUsername(row) })"
                                                    class="ghost-action"
                                                >
                                                    <span class="ghost-action__icon">↗</span>
                                                    <span>Profile</span>
                                                </Link>
                                                <Link
                                                    v-if="row.is_app_user && row.has_public_dashboard && row.linked_minecraft_uuid"
                                                    :href="route('dashboard.visit', { minecraftUuid: row.linked_minecraft_uuid })"
                                                    class="ghost-action ghost-action--accent"
                                                >
                                                    <span class="ghost-action__icon">▣</span>
                                                    <span>Dashboard</span>
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="leaderboards-mobile-grid">
                            <article v-for="row in leaderboardData.rows" :key="`mobile-${row.user_id ?? row.minecraft_uuid}-${row.rank}`" class="leaderboard-mobile-card">
                                <div class="leaderboard-mobile-card__top">
                                    <div>
                                        <p class="leaderboard-mobile-card__kicker">Rank #{{ row.rank }}</p>
                                        <h3 class="leaderboard-mobile-card__title">{{ formatDisplayName(row.display_name || row.profile_username || row.minecraft_uuid) }}</h3>
                                    </div>
                                    <span class="rank-badge">{{ row.rank }}</span>
                                </div>

                                <div class="leaderboard-mobile-card__meta">
                                    <span v-if="row.hypixel_rank" class="player-rank" :style="{ color: row.hypixel_rank_color || '#94a3b8' }">{{ row.hypixel_rank }}</span>
                                    <span v-if="row.is_app_user" class="player-flag">App user</span>
                                    <span class="movement-chip" :class="movementBadge(row)?.icon === '▲' ? 'movement-chip--up' : (movementBadge(row)?.icon === '▼' ? 'movement-chip--down' : '')">
                                        <template v-if="movementBadge(row)">{{ movementBadge(row).icon }} {{ movementBadge(row).label }}</template>
                                        <template v-else>—</template>
                                    </span>
                                </div>

                                <dl class="leaderboard-mobile-card__stats">
                                    <div>
                                        <dt>Level</dt>
                                        <dd>{{ getColumnValue(row, 'level') }}</dd>
                                    </div>
                                    <div>
                                        <dt>Networth</dt>
                                        <dd>{{ getColumnValue(row, 'networth') }}</dd>
                                    </div>
                                    <div>
                                        <dt>Skill Avg</dt>
                                        <dd>{{ getColumnValue(row, 'skill_average') }}</dd>
                                    </div>
                                    <div>
                                        <dt>Slayer XP</dt>
                                        <dd>{{ getColumnValue(row, 'slayer_total') }}</dd>
                                    </div>
                                    <div>
                                        <dt>Age</dt>
                                        <dd>{{ getColumnValue(row, 'account_age') }}</dd>
                                    </div>
                                    <div>
                                        <dt>Visits</dt>
                                        <dd>{{ getColumnValue(row, 'profile_visits') }}</dd>
                                    </div>
                                    <div>
                                        <dt>Status</dt>
                                        <dd>{{ getStatusLabel(row) }}</dd>
                                    </div>
                                </dl>

                                <div class="leaderboard-mobile-card__actions">
                                    <Link
                                        v-if="getActionUsername(row)"
                                        :href="route('profile-stats', { username: getActionUsername(row) })"
                                        class="ghost-action"
                                    >
                                        <span class="ghost-action__icon">↗</span>
                                        <span>Profile</span>
                                    </Link>
                                    <Link
                                        v-if="row.is_app_user && row.has_public_dashboard && row.linked_minecraft_uuid"
                                        :href="route('dashboard.visit', { minecraftUuid: row.linked_minecraft_uuid })"
                                        class="ghost-action ghost-action--accent"
                                    >
                                        <span class="ghost-action__icon">▣</span>
                                        <span>Dashboard</span>
                                    </Link>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>

                <section v-if="leaderboardData?.pagination" class="leaderboards-pagination">
                    <button type="button" class="pagination-btn" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">
                        Previous
                    </button>
                    <span class="pagination-info">
                        Page {{ leaderboardData.pagination.current_page }} of {{ leaderboardData.pagination.last_page }}
                    </span>
                    <button
                        type="button"
                        class="pagination-btn"
                        :disabled="currentPage === leaderboardData.pagination.last_page"
                        @click="goToPage(currentPage + 1)"
                    >
                        Next
                    </button>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.leaderboards-page {
    min-height: 100%;
    background: transparent;
    color: #fff;
    font-family: 'Inter', sans-serif;
}

.leaderboards-shell {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.leaderboards-hero {
    display: grid;
    gap: 1rem;
}

.leaderboards-kicker {
    font-size: 0.73rem;
    font-weight: 600;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: rgba(148, 163, 184, 0.95);
}

.leaderboards-hero__copy {
    display: grid;
    gap: 0.55rem;
}

.leaderboards-title {
    margin: 0;
    font-size: clamp(2rem, 4vw, 3.1rem);
    line-height: 1;
    font-weight: 600;
    letter-spacing: -0.04em;
}

.leaderboards-copy {
    margin: 0;
    max-width: 72ch;
    color: rgba(148, 163, 184, 0.96);
    line-height: 1.6;
    font-size: 0.95rem;
}

.leaderboards-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.meta-card {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 160px;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.03);
    padding: 0.85rem 1rem;
}

.meta-card__label {
    font-size: 0.72rem;
    font-weight: 400;
    color: rgba(148, 163, 184, 0.82);
    text-transform: uppercase;
    letter-spacing: 0.14em;
}

.meta-card__value {
    font-size: 0.95rem;
    font-weight: 500;
    color: #fff;
}

.leaderboards-controls {
    display: grid;
    gap: 0.75rem;
}

.leaderboards-personal {
    margin-top: 0.2rem;
}

.personal-card {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-radius: 18px;
    border: 1px solid rgba(148, 163, 184, 0.2);
    background: rgba(15, 23, 42, 0.75);
    padding: 1rem 1.2rem;
}

.personal-kicker {
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.18em;
    font-size: 0.62rem;
    font-weight: 700;
    color: rgba(148, 163, 184, 0.86);
}

.personal-title {
    margin: 0.3rem 0 0;
    font-size: 1.05rem;
    font-weight: 700;
}

.personal-copy {
    margin: 0.35rem 0 0;
    color: rgba(148, 163, 184, 0.9);
    font-size: 0.82rem;
}

.personal-rank {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    align-items: flex-end;
}

.personal-rank__label {
    font-size: 0.7rem;
    color: rgba(148, 163, 184, 0.9);
    text-transform: uppercase;
    letter-spacing: 0.14em;
}

.personal-rank__value {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.4rem;
    font-weight: 700;
}

.leaderboards-podium {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.06fr) minmax(0, 1fr);
    grid-template-areas: 'second first third';
    gap: 0.65rem;
    align-items: end;
}

.podium-card {
    --step-height: 30px;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.4rem;
    border-radius: 16px;
    padding: 0.82rem 0.82rem 0.88rem;
    background: rgba(15, 17, 26, 0.78);
    border: 1px solid rgba(148, 163, 184, 0.14);
    box-shadow: 0 12px 36px rgba(0, 0, 0, 0.16);
    text-align: center;
    min-height: 188px;
    overflow: hidden;
}

.podium-card > * {
    position: relative;
    z-index: 1;
}

.podium-card::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: var(--step-height);
    background: linear-gradient(180deg, rgba(148, 163, 184, 0.06) 0%, rgba(148, 163, 184, 0.14) 100%);
    border-top: 1px solid rgba(148, 163, 184, 0.2);
    z-index: 0;
}

.podium-card--first {
    grid-area: first;
    --step-height: 56px;
    transform: translateY(-0.6rem);
    min-height: 226px;
    border-color: rgba(251, 191, 36, 0.28);
    box-shadow: 0 18px 44px rgba(251, 191, 36, 0.08), 0 12px 36px rgba(0, 0, 0, 0.16);
}

.podium-card--second {
    grid-area: second;
    --step-height: 40px;
    min-height: 206px;
    transform: translateY(0.25rem);
}

.podium-card--third {
    grid-area: third;
    --step-height: 26px;
    min-height: 190px;
    transform: translateY(0.58rem);
}

.podium-card__rank {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.18em;
    color: rgba(148, 163, 184, 0.95);
    text-transform: uppercase;
}

.podium-card__avatar {
    width: 70px;
    height: 70px;
    border-radius: 18px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(255, 255, 255, 0.03);
    object-fit: cover;
}

.podium-card__name {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    align-items: center;
    min-width: 0;
}

.podium-card__name strong {
    font-size: 0.92rem;
    font-weight: 600;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.podium-card__stat-label {
    font-size: 0.66rem;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(148, 163, 184, 0.82);
}

.podium-card__stat-value {
    font-size: 1.05rem;
    font-weight: 700;
    color: #fff;
}

.chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.chip {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.03);
    color: rgba(226, 232, 240, 0.88);
    padding: 0.6rem 0.95rem;
    font-size: 0.82rem;
    font-weight: 500;
    cursor: pointer;
    transition: border-color 160ms ease, background-color 160ms ease, color 160ms ease, transform 160ms ease;
}

.chip:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(148, 163, 184, 0.32);
    transform: translateY(-1px);
}

.chip--active {
    background: rgba(56, 189, 248, 0.12);
    border-color: rgba(56, 189, 248, 0.45);
    color: #fff;
}

.chip--sort .chip__direction {
    color: rgba(148, 163, 184, 0.95);
    font-size: 0.78rem;
}

.leaderboards-table-shell {
    margin-top: 0.35rem;
    position: relative;
    border-radius: 22px;
    background: rgba(15, 17, 26, 0.82);
    box-shadow: 0 16px 52px rgba(0, 0, 0, 0.18);
    overflow: hidden;
}

.leaderboards-loading,
.leaderboards-empty {
    display: grid;
    place-items: center;
    min-height: 220px;
    gap: 0.75rem;
    color: rgba(148, 163, 184, 0.92);
}

.loading-bar {
    width: 140px;
    height: 3px;
    border-radius: 999px;
    background: linear-gradient(90deg, rgba(56, 189, 248, 0.12), rgba(56, 189, 248, 0.85), rgba(251, 191, 36, 0.6));
    background-size: 200% 100%;
    animation: loading-shift 1.2s linear infinite;
}

.loading-text {
    font-size: 0.9rem;
    font-weight: 500;
}

.leaderboards-table-wrap {
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
}

.leaderboards-mobile-grid {
    display: none;
    gap: 0.7rem;
}

.leaderboard-mobile-card {
    border-radius: 16px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(15, 17, 26, 0.82);
    padding: 0.85rem;
}

.leaderboard-mobile-card__top {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    align-items: flex-start;
}

.leaderboard-mobile-card__kicker {
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.18em;
    font-size: 0.62rem;
    font-weight: 700;
    color: rgba(148, 163, 184, 0.82);
}

.leaderboard-mobile-card__title {
    margin: 0.3rem 0 0;
    color: #fff;
    font-size: 0.95rem;
    font-weight: 700;
    line-height: 1.25;
}

.leaderboard-mobile-card__meta {
    margin-top: 0.6rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.leaderboard-mobile-card__stats {
    margin-top: 0.75rem;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.55rem;
}

.leaderboard-mobile-card__stats dt {
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgba(148, 163, 184, 0.82);
}

.leaderboard-mobile-card__stats dd {
    margin: 0.14rem 0 0;
    color: #f8fafc;
    font-size: 0.8rem;
    font-weight: 600;
}

.leaderboard-mobile-card__actions {
    margin-top: 0.75rem;
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.leaderboards-table {
    width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
}

.leaderboards-table thead th {
    border-bottom: 1px solid rgba(148, 163, 184, 0.14);
    background: rgba(255, 255, 255, 0.02);
    padding: 0.6rem 0.55rem;
    text-align: left;
    color: rgba(226, 232, 240, 0.9);
    font-size: 0.65rem;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    white-space: nowrap;
}

.leaderboards-table tbody td {
    border-bottom: 1px solid rgba(148, 163, 184, 0.1);
    padding: 0.72rem 0.55rem;
    vertical-align: middle;
    color: rgba(255, 255, 255, 0.92);
    font-size: 0.84rem;
}

.leaderboard-row {
    transition: background-color 160ms ease;
}

.leaderboard-row:hover {
    background: rgba(255, 255, 255, 0.03);
}

.col-rank {
    width: 52px;
    text-align: center;
}

.col-movement {
    width: 60px;
    text-align: center;
}

.col-player {
    width: 190px;
}

.col-status {
    width: 122px;
    text-align: left;
}

.col-actions {
    width: 136px;
}

.align-right {
    text-align: right;
}

.th-button {
    display: inline-flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 0.55rem;
    border: 0;
    background: transparent;
    color: inherit;
    padding: 0;
    cursor: pointer;
    text-align: inherit;
    font: inherit;
}

.th-button__sort {
    color: rgba(148, 163, 184, 0.75);
    font-size: 0.67rem;
    font-weight: 600;
}

.th-button__sort--active {
    color: #fff;
}

.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.7rem;
    height: 1.7rem;
    border-radius: 0.65rem;
    background: rgba(255, 255, 255, 0.06);
    color: #fff;
    font-size: 0.78rem;
    font-weight: 600;
}

.movement-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.45rem;
    border-radius: 999px;
    border: 1px solid rgba(148, 163, 184, 0.2);
    font-size: 0.7rem;
    color: rgba(226, 232, 240, 0.9);
    background: rgba(15, 23, 42, 0.6);
}

.movement-chip--up {
    border-color: rgba(74, 222, 128, 0.35);
    color: #4ade80;
}

.movement-chip--down {
    border-color: rgba(248, 113, 113, 0.4);
    color: #f87171;
}

.movement-chip--neutral {
    color: rgba(148, 163, 184, 0.8);
}

.player-cell {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}

.player-avatar {
    width: 20px;
    height: 20px;
    border-radius: 6px;
    object-fit: cover;
    flex-shrink: 0;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(255, 255, 255, 0.03);
}

.player-rank {
    flex-shrink: 0;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.02em;
}

.player-name-wrap {
    display: grid;
    gap: 0.1rem;
    min-width: 0;
}

.player-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-weight: 500;
    color: #fff;
    font-size: 0.84rem;
}

.player-flag {
    width: fit-content;
    border-radius: 999px;
    padding: 0.14rem 0.38rem;
    font-size: 0.6rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(148, 163, 184, 0.95);
    background: rgba(255, 255, 255, 0.04);
}

.status-text {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 500;
    white-space: normal;
    line-height: 1.15;
}

.status-text::before {
    content: '';
    width: 0.52rem;
    height: 0.52rem;
    border-radius: 999px;
    flex-shrink: 0;
}

.status-text--online {
    color: #d1fae5;
}

.status-text--online::before {
    background: #22c55e;
    box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.12);
}

.status-text--offline {
    color: rgba(148, 163, 184, 0.95);
}

.status-text--offline::before {
    background: #64748b;
    box-shadow: 0 0 0 4px rgba(100, 116, 139, 0.12);
}

.row-actions {
    display: flex;
    justify-content: flex-start;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.ghost-action {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: rgba(255, 255, 255, 0.03);
    color: rgba(255, 255, 255, 0.88);
    padding: 0.3rem 0.5rem;
    font-size: 0.66rem;
    font-weight: 500;
    text-decoration: none;
    transition: border-color 160ms ease, background-color 160ms ease, color 160ms ease;
}

.ghost-action:hover {
    border-color: rgba(56, 189, 248, 0.42);
    background: rgba(56, 189, 248, 0.1);
    color: #fff;
}

.ghost-action--accent:hover {
    border-color: rgba(16, 185, 129, 0.45);
    background: rgba(16, 185, 129, 0.12);
}

.ghost-action__icon {
    font-size: 0.68rem;
    opacity: 0.88;
}

.leaderboards-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding-top: 0.5rem;
}

.pagination-btn {
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.03);
    color: rgba(226, 232, 240, 0.95);
    padding: 0.55rem 0.95rem;
    font-size: 0.82rem;
    font-weight: 500;
    cursor: pointer;
    transition: border-color 160ms ease, background-color 160ms ease, transform 160ms ease;
}

.pagination-btn:hover:not(:disabled) {
    border-color: rgba(56, 189, 248, 0.4);
    background: rgba(56, 189, 248, 0.08);
    transform: translateY(-1px);
}

.pagination-btn:disabled {
    opacity: 0.42;
    cursor: not-allowed;
}

.pagination-info {
    color: rgba(148, 163, 184, 0.9);
    font-size: 0.82rem;
    font-weight: 500;
}

@keyframes loading-shift {
    from {
        background-position: 0 0;
    }

    to {
        background-position: 200% 0;
    }
}

@media (max-width: 1200px) {
    .col-slayer_total {
        display: none;
    }
}

@media (max-width: 900px) {
    .col-account_age,
    .col-profile_visits {
        display: none;
    }

    .leaderboards-podium {
        grid-template-columns: 1fr;
        grid-template-areas: none;
    }

    .podium-card--first,
    .podium-card--second,
    .podium-card--third {
        --step-height: 30px;
        grid-area: auto;
        transform: none;
    }

    .leaderboards-pagination {
        justify-content: space-between;
    }
}

@media (max-width: 640px) {
    .leaderboards-table-wrap--desktop {
        display: none;
    }

    .leaderboards-mobile-grid {
        display: grid;
    }

    .leaderboards-meta {
        flex-direction: column;
    }

    .leaderboards-table thead th,
    .leaderboards-table tbody td {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .col-player {
        width: 200px;
    }
}
</style>
