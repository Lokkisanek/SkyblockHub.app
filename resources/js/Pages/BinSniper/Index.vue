<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { getItemTextureUrl } from '@/utils/textures';

const props = defineProps({
    snipes: Array,
    alerts: Array,
    filters: Object,
    constraints: Object,
});

const search = ref(props.filters.search || '');
const tier = ref(props.filters.tier || '');
const sort = ref(props.filters.sort || 'detected_at');
const direction = ref(props.filters.direction || 'desc');

const liveSnipes = ref(props.snipes || []);
const isRefreshing = ref(false);
const lastFeedUpdate = ref(new Date().toISOString());
const refreshError = ref('');
const lastRefreshMs = ref(null);
const soundEnabled = ref(false);
const copiedUuid = ref('');
const copiedPins = ref({});
const nowTickMs = ref(Date.now());

const FEED_REFRESH_MS = 15000;
const MAX_FEED_ITEMS = 25;
const COPY_PIN_DURATION_MS = 5 * 60 * 1000;
let feedIntervalId = null;
let pinTickIntervalId = null;
let searchTimeout = null;

watch(
    () => props.snipes,
    (next) => {
        liveSnipes.value = mergeWithPinned(Array.isArray(next) ? next : []);
    },
);

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 350);
});

watch([tier, sort, direction], () => applyFilters());

const feedSnipes = computed(() => liveSnipes.value.slice(0, MAX_FEED_ITEMS));

function applyFilters() {
    router.get(route('bin-sniper'), {
        search: search.value || undefined,
        tier: tier.value || undefined,
        sort: sort.value,
        direction: direction.value,
    }, { preserveState: true, preserveScroll: true, only: ['snipes', 'filters', 'constraints'] });
}

function fmtCoins(value) {
    if (value === null || value === undefined) return '—';
    return Number(value).toLocaleString('en-US', { maximumFractionDigits: 0 });
}

function fmtPercent(value) {
    if (value === null || value === undefined) return '—';
    return `${Number(value).toFixed(1)}%`;
}

function timeAgo(dateStr) {
    if (!dateStr) return '—';
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
}

function tierColor(tierName) {
    const map = {
        COMMON: 'text-rarity-common',
        UNCOMMON: 'text-rarity-uncommon',
        RARE: 'text-rarity-rare',
        EPIC: 'text-rarity-epic',
        LEGENDARY: 'text-rarity-legendary',
        MYTHIC: 'text-rarity-mythic',
        DIVINE: 'text-rarity-divine',
        SPECIAL: 'text-rarity-mythic',
        VERY_SPECIAL: 'text-rarity-divine',
    };
    return map[tierName] || 'text-neutral';
}

function itemTexture(snipe) {
    return getItemTextureUrl({ texture_path: snipe.texture_path }) || '/img/textures/paper.png';
}

function onTextureError(event) {
    event.target.src = '/img/textures/paper.png';
}

function pruneExpiredPins() {
    const now = Date.now();
    const entries = Object.entries(copiedPins.value).filter(([, expiresAt]) => Number(expiresAt) > now);
    copiedPins.value = Object.fromEntries(entries);
}

function isPinned(auctionUuid) {
    const expiresAt = Number(copiedPins.value[auctionUuid] || 0);
    return expiresAt > nowTickMs.value;
}

function pinCountdown(auctionUuid) {
    const expiresAt = Number(copiedPins.value[auctionUuid] || 0);
    const remainingMs = Math.max(0, expiresAt - nowTickMs.value);
    const totalSeconds = Math.floor(remainingMs / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

function mergeWithPinned(incomingSnipes) {
    pruneExpiredPins();

    const incoming = Array.isArray(incomingSnipes) ? incomingSnipes : [];
    const incomingByUuid = new Set(incoming.map((entry) => entry.auction_uuid));

    const pinnedCarryOver = (liveSnipes.value || []).filter(
        (entry) => isPinned(entry.auction_uuid) && !incomingByUuid.has(entry.auction_uuid),
    );

    return [...incoming, ...pinnedCarryOver].slice(0, MAX_FEED_ITEMS);
}

async function copyViewAuction(snipe) {
    const command = snipe.viewauction_command || `/viewauction ${snipe.auction_uuid}`;
    await navigator.clipboard.writeText(command);
    copiedPins.value = {
        ...copiedPins.value,
        [snipe.auction_uuid]: Date.now() + COPY_PIN_DURATION_MS,
    };
    copiedUuid.value = snipe.auction_uuid;
    setTimeout(() => {
        if (copiedUuid.value === snipe.auction_uuid) copiedUuid.value = '';
    }, 1400);
}

function playDing() {
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    oscillator.type = 'triangle';
    oscillator.frequency.setValueAtTime(880, audioContext.currentTime);
    gainNode.gain.setValueAtTime(0.18, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + 0.25);
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    oscillator.start();
    oscillator.stop(audioContext.currentTime + 0.25);
}

async function refreshFeed() {
    if (isRefreshing.value) return;
    isRefreshing.value = true;
    refreshError.value = '';

    const start = performance.now();
    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), 12000);

    try {
        const known = new Set((liveSnipes.value || []).map((entry) => entry.auction_uuid));
        const response = await fetch(route('bin-sniper', {
            search: search.value || undefined,
            tier: tier.value || undefined,
            sort: sort.value,
            direction: direction.value,
            feed: 1,
        }), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });

        if (!response.ok) {
            refreshError.value = `Feed refresh failed (${response.status}).`;
            return;
        }

        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            refreshError.value = 'Feed returned unexpected response format.';
            return;
        }

        const payload = await response.json();
        const nextSnipes = Array.isArray(payload.snipes) ? payload.snipes.slice(0, MAX_FEED_ITEMS) : [];

        const hasNewHighProfit = nextSnipes.some(
            (entry) => !known.has(entry.auction_uuid) && Number(entry.profit_margin) >= 2000000,
        );

        liveSnipes.value = mergeWithPinned(nextSnipes);
        lastFeedUpdate.value = payload.generated_at || new Date().toISOString();

        if (soundEnabled.value && hasNewHighProfit) {
            playDing();
        }
    } catch (error) {
        if (error?.name === 'AbortError') {
            refreshError.value = 'Feed refresh timeout.';
        } else {
            refreshError.value = 'Feed refresh failed. Check server state.';
        }
    } finally {
        window.clearTimeout(timeoutId);
        lastRefreshMs.value = Math.round(performance.now() - start);
        isRefreshing.value = false;
    }
}

onMounted(() => {
    refreshFeed();
    feedIntervalId = window.setInterval(refreshFeed, FEED_REFRESH_MS);
    pinTickIntervalId = window.setInterval(() => {
        nowTickMs.value = Date.now();
        pruneExpiredPins();
    }, 1000);
});

onBeforeUnmount(() => {
    clearTimeout(searchTimeout);
    if (feedIntervalId) {
        window.clearInterval(feedIntervalId);
        feedIntervalId = null;
    }
    if (pinTickIntervalId) {
        window.clearInterval(pinTickIntervalId);
        pinTickIntervalId = null;
    }
});
</script>

<template>
    <Head title="BIN Sniper Live Feed" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-sm font-semibold text-white uppercase tracking-wide">BIN Sniper Live Feed</h2>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
            <div class="mb-4 flex flex-wrap items-center gap-3 border border-border bg-gradient-to-r from-surface-800 to-surface-700 p-3 shadow-[0_0_0_1px_rgba(90,110,130,0.12)]">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search items…"
                    class="bg-surface-800 border border-border text-xs text-white px-3 py-1.5 placeholder-neutral focus:outline-none focus:border-border-light w-56"
                />
                <select
                    v-model="tier"
                    class="bg-surface-800 border border-border text-xs text-white px-3 py-1.5 focus:outline-none focus:border-border-light"
                >
                    <option value="">All Tiers</option>
                    <option value="COMMON">Common</option>
                    <option value="UNCOMMON">Uncommon</option>
                    <option value="RARE">Rare</option>
                    <option value="EPIC">Epic</option>
                    <option value="LEGENDARY">Legendary</option>
                    <option value="MYTHIC">Mythic</option>
                    <option value="DIVINE">Divine</option>
                </select>
                <select
                    v-model="sort"
                    class="bg-surface-800 border border-border text-xs text-white px-3 py-1.5 focus:outline-none focus:border-border-light"
                >
                    <option value="detected_at">Newest</option>
                    <option value="profit">Profit</option>
                    <option value="confidence">Confidence</option>
                    <option value="score">Score</option>
                    <option value="price">LBIN</option>
                </select>
                <button
                    class="bg-surface-700 border border-border px-2 py-1 text-xs text-white hover:bg-surface-600"
                    @click="direction = direction === 'desc' ? 'asc' : 'desc'"
                >
                    {{ direction === 'desc' ? 'Desc' : 'Asc' }}
                </button>
                <label class="ml-auto inline-flex items-center gap-2 text-[11px] text-neutral">
                    <input v-model="soundEnabled" type="checkbox" class="accent-profit" />
                    Ding on high-profit snipes
                </label>
                <button
                    class="border border-border bg-surface-700 px-2 py-1 text-xs text-white hover:bg-surface-600"
                    :disabled="isRefreshing"
                    @click="refreshFeed"
                >
                    {{ isRefreshing ? 'Refreshing…' : 'Refresh now' }}
                </button>
            </div>

            <div class="mb-4 flex flex-wrap gap-4 text-[11px] text-neutral">
                <span>Feed updated: {{ timeAgo(lastFeedUpdate) }}</span>
                <span>Refresh time: {{ lastRefreshMs !== null ? `${lastRefreshMs}ms` : '—' }}</span>
                <span>Min Profit: {{ fmtCoins(constraints?.minimum_profit) }}</span>
                <span>Min %: {{ constraints?.minimum_percentage }}%</span>
                <span>Manipulated: ignored</span>
                <span>Items in feed: {{ feedSnipes.length }}</span>
            </div>

            <div v-if="refreshError" class="mb-4 border border-loss/50 bg-loss/10 px-3 py-2 text-xs text-loss">
                {{ refreshError }}
            </div>

            <transition-group name="feed" tag="div" class="grid gap-3">
                <article
                    v-for="snipe in feedSnipes"
                    :key="snipe.auction_uuid"
                    class="border bg-surface-800 p-3"
                    :class="isPinned(snipe.auction_uuid) ? 'border-profit/60' : 'border-border'"
                >
                    <div class="flex flex-wrap items-start gap-3">
                        <img
                            :src="itemTexture(snipe)"
                            :alt="snipe.item_name"
                            class="h-10 w-10 border border-border bg-surface-700 object-contain p-1"
                            loading="lazy"
                            @error="onTextureError"
                        />

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="truncate text-sm font-semibold text-white">{{ snipe.item_name }}</h3>
                                <span class="text-[11px]" :class="tierColor(snipe.tier)">{{ snipe.tier || 'UNKNOWN' }}</span>
                                <span class="text-[10px] uppercase tracking-wider text-neutral">Seen {{ timeAgo(snipe.detected_at) }}</span>
                            </div>

                            <div class="mt-2 grid gap-2 text-xs sm:grid-cols-4">
                                <div class="border border-border/70 bg-surface-700 px-2 py-1">
                                    <div class="text-[10px] uppercase text-neutral">LBIN Buy</div>
                                    <div class="text-rarity-legendary">{{ fmtCoins(snipe.lbin) }}</div>
                                </div>
                                <div class="border border-border/70 bg-surface-700 px-2 py-1">
                                    <div class="text-[10px] uppercase text-neutral">SLBIN List</div>
                                    <div class="text-rarity-legendary">{{ fmtCoins(snipe.slbin) }}</div>
                                </div>
                                <div class="border border-border/70 bg-surface-700 px-2 py-1">
                                    <div class="text-[10px] uppercase text-neutral">Potential Profit</div>
                                    <div class="text-profit font-semibold">{{ fmtCoins(snipe.profit_margin) }}</div>
                                </div>
                                <div class="border border-border/70 bg-surface-700 px-2 py-1">
                                    <div class="text-[10px] uppercase text-neutral">Profit %</div>
                                    <div class="text-profit font-semibold">{{ fmtPercent(snipe.profit_percentage) }}</div>
                                </div>
                            </div>

                            <div class="mt-2 border border-border/70 bg-surface-700 px-2 py-2">
                                <div class="mb-1 flex items-center justify-between text-[10px] uppercase tracking-wider text-neutral">
                                    <span>Confidence Score</span>
                                    <span class="text-white">{{ Number(snipe.confidence_score).toFixed(1) }}%</span>
                                </div>
                                <div class="h-2 w-full overflow-hidden bg-surface-900">
                                    <div
                                        class="h-2 bg-profit transition-all duration-500"
                                        :style="{ width: `${Math.max(0, Math.min(100, Number(snipe.confidence_score || 0)))}%` }"
                                    ></div>
                                </div>
                            </div>

                            <div class="mt-2 grid gap-1 text-[11px] text-neutral sm:grid-cols-2 lg:grid-cols-4">
                                <span>Score: {{ fmtCoins(snipe.score) }}</span>
                                <span>Liquidity: {{ Number(snipe.item_liquidity).toFixed(1) }}/100</span>
                                <span>24h Avg: {{ fmtCoins(snipe.avg_price_24h) }}</span>
                                <span>Active Auctions: {{ snipe.active_auctions }}</span>
                            </div>

                            <div class="mt-2 border border-border/70 bg-surface-700 px-2 py-2 text-[11px] text-neutral">
                                <div class="text-[10px] uppercase tracking-wider">Profit Calculator (Instant Relist)</div>
                                <div class="mt-1 grid gap-1 sm:grid-cols-4">
                                    <span>Buy: {{ fmtCoins(snipe.lbin) }}</span>
                                    <span>Relist: {{ fmtCoins(snipe.slbin) }}</span>
                                    <span>Tax (1%): {{ fmtCoins(snipe.tax_amount) }}</span>
                                    <span class="text-profit">Net: {{ fmtCoins(snipe.profit_after_tax) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="w-full sm:w-auto">
                            <div class="border border-border bg-surface-700 p-2 text-[11px] text-neutral">
                                <div class="text-[10px] uppercase tracking-wider">Command</div>
                                <div class="mt-1 break-all text-white">{{ snipe.viewauction_command }}</div>
                                <div v-if="isPinned(snipe.auction_uuid)" class="mt-1 text-[10px] text-profit">
                                    Pinned after copy: {{ pinCountdown(snipe.auction_uuid) }}
                                </div>
                                <button
                                    class="mt-2 w-full border border-border-light bg-surface-600 px-2 py-1 text-[11px] text-white hover:bg-surface-500"
                                    @click="copyViewAuction(snipe)"
                                >
                                    {{ copiedUuid === snipe.auction_uuid ? 'Copied' : 'Copy /viewauction UUID' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </article>
            </transition-group>

            <div v-if="!feedSnipes.length" class="border border-border bg-surface-800 px-3 py-8 text-center text-xs text-neutral">
                No snipes passed filters yet. Run <code class="text-white">php artisan bin:fetch</code> and wait for next feed refresh.
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.feed-enter-active,
.feed-leave-active {
    transition: all 0.35s ease;
}

.feed-enter-from {
    opacity: 0;
    transform: translateY(-10px);
}

.feed-leave-to {
    opacity: 0;
    transform: translateY(10px);
}
</style>
