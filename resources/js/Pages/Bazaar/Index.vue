<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    items: Object,
    filters: Object,
});

const RARITY_COLORS = {
    common: '#FFFFFF',
    uncommon: '#55FF55',
    rare: '#5555FF',
    epic: '#AA00AA',
    legendary: '#FFAA00',
    mythic: '#FF55FF',
    divine: '#55FFFF',
    special: '#FF5555',
};

// Static NPC sell values used for NPC flip highlighting.
const NPC_SELL_PRICES = {
    WHEAT: 6,
    CARROT_ITEM: 3,
    POTATO_ITEM: 3,
    PUMPKIN: 10,
    MELON: 2,
    SUGAR_CANE: 4,
    COCOA_BEANS: 3,
    RED_MUSHROOM: 10,
    BROWN_MUSHROOM: 10,
    ROTTEN_FLESH: 2,
    STRING: 3,
    BONE: 2,
    SPIDER_EYE: 3,
    GUNPOWDER: 4,
    ENDER_PEARL: 10,
    OBSIDIAN: 7,
    GOLD_INGOT: 6,
    IRON_INGOT: 3,
    COAL: 2,
    DIAMOND: 8,
    ENCHANTED_DIAMOND: 1280,
};

const liveItems = ref([...props.items.data]);
const failedIcons = ref({});
const buyTrend = ref({});
const sellTrend = ref({});

const search = ref(props.filters.search || '');
const sortBy = ref(props.filters.sort || 'profit_score');
const sortDir = ref(props.filters.dir || 'desc');
const minDailyVolume = ref(Number(props.filters.min_daily_volume ?? 1000));
const maxBuyPrice = ref(props.filters.max_buy_price ?? '');
const minTrueProfit = ref(props.filters.min_true_profit ?? '');
const minMarginPercent = ref(props.filters.min_margin_percent ?? '');

let debounceTimer = null;
let echoChannel = null;

watch(() => props.items.data, (newData) => {
    liveItems.value = [...newData];
});

watch([search, minDailyVolume, maxBuyPrice, minTrueProfit, minMarginPercent], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 260);
});

onMounted(() => {
    if (!window.Echo) {
        return;
    }

    echoChannel = window.Echo.channel('bazaar');
    echoChannel.listen('.data.updated', (e) => {
        if (!e.items) return;

        liveItems.value = liveItems.value.map((item) => {
            const update = e.items[item.product_id];
            if (!update) return item;

            const prevBuy = Number(item.buy_price || 0);
            const prevSell = Number(item.sell_price || 0);
            const nextBuy = Number(update.buy_price || 0);
            const nextSell = Number(update.sell_price || 0);

            buyTrend.value[item.product_id] = trendDirection(prevBuy, nextBuy);
            sellTrend.value[item.product_id] = trendDirection(prevSell, nextSell);

            return {
                ...item,
                sell_price: nextSell,
                buy_price: nextBuy,
                sell_volume: Number(update.sell_volume || 0),
                buy_volume: Number(update.buy_volume || 0),
                sell_orders: Number(update.sell_orders || 0),
                buy_orders: Number(update.buy_orders || 0),
                true_profit: trueProfit({ ...item, sell_price: nextSell, buy_price: nextBuy }),
            };
        });
    });
});

onUnmounted(() => {
    if (echoChannel && window.Echo) {
        window.Echo.leave('bazaar');
    }

    clearTimeout(debounceTimer);
});

function applyFilters() {
    router.get(route('bazaar'), {
        search: search.value || undefined,
        min_daily_volume: minDailyVolume.value > 0 ? minDailyVolume.value : 0,
        max_buy_price: maxBuyPrice.value === '' ? undefined : maxBuyPrice.value,
        min_true_profit: minTrueProfit.value === '' ? undefined : minTrueProfit.value,
        min_margin_percent: minMarginPercent.value === '' ? undefined : minMarginPercent.value,
        sort: sortBy.value,
        dir: sortDir.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function toggleSort(column) {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDir.value = 'desc';
    }

    applyFilters();
}

function sortIndicator(column) {
    if (sortBy.value !== column) return '';
    return sortDir.value === 'asc' ? ' ^' : ' v';
}

function trendDirection(previous, current) {
    if (current > previous) return 'up';
    if (current < previous) return 'down';
    return 'flat';
}

function trendArrow(direction) {
    if (direction === 'up') return '^';
    if (direction === 'down') return 'v';
    return '-';
}

function trendClass(direction) {
    if (direction === 'up') return 'text-emerald-300';
    if (direction === 'down') return 'text-rose-300';
    return 'text-neutral';
}

function formatCoins(value) {
    if (value === null || value === undefined) return '-';
    return Number(value).toLocaleString('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
}

function formatCompact(value) {
    if (value === null || value === undefined) return '-';

    const num = Number(value);
    const abs = Math.abs(num);

    if (abs >= 1_000_000_000) return `${(num / 1_000_000_000).toFixed(1)}B`;
    if (abs >= 1_000_000) return `${(num / 1_000_000).toFixed(1)}M`;
    if (abs >= 1_000) return `${(num / 1_000).toFixed(1)}K`;
    return num.toFixed(0);
}

function trueProfit(item) {
    return (Number(item.sell_price || 0) * 0.9875) - Number(item.buy_price || 0);
}

function marginPercent(item) {
    const sell = Number(item.sell_price || 0);
    if (sell <= 0) return 0;
    return (trueProfit(item) / sell) * 100;
}

function volumeRatio(item) {
    const sell = Number(item.sell_volume || 0);
    const buy = Number(item.buy_volume || 0);
    if (buy <= 0) return sell > 0 ? 99 : 0;
    return sell / buy;
}

function volatilityScore(item) {
    const sell = Number(item.sell_volume || 0);
    const buy = Number(item.buy_volume || 0);
    const sum = sell + buy;
    if (sum <= 0) return 0;

    // 0..100, higher means less stable volume profile.
    return (Math.abs(sell - buy) / sum) * 100;
}

const statCaps = computed(() => {
    let maxMargin = 0;
    let maxVolume = 0;

    for (const item of liveItems.value) {
        maxMargin = Math.max(maxMargin, Math.max(0, trueProfit(item)));
        maxVolume = Math.max(maxVolume, Number(item.sell_volume || 0) + Number(item.buy_volume || 0));
    }

    return {
        maxMargin: maxMargin <= 0 ? 1 : maxMargin,
        maxVolume: maxVolume <= 0 ? 1 : maxVolume,
    };
});

function flippingScore(item) {
    const marginNorm = Math.max(0, (trueProfit(item) / statCaps.value.maxMargin) * 100);
    const volumeNorm = ((Number(item.sell_volume || 0) + Number(item.buy_volume || 0)) / statCaps.value.maxVolume) * 100;
    const volatility = volatilityScore(item);

    const score = (marginNorm * 0.7) + (volumeNorm * 0.3) - (volatility * 0.1);
    return Math.max(0, Math.min(100, score));
}

function npcSellPrice(item) {
    return NPC_SELL_PRICES[String(item.product_id || '').toUpperCase()] ?? null;
}

function npcProfit(item) {
    const npc = npcSellPrice(item);
    if (npc === null) return 0;
    return npc - Number(item.sell_price || 0);
}

function isNpcProfit(item) {
    return npcProfit(item) > 0;
}

function isHotItem(item) {
    return flippingScore(item) >= 78 && Number(item.sell_volume || 0) >= Math.max(1500, minDailyVolume.value);
}

function marginClass(item) {
    const margin = trueProfit(item);
    if (margin > 0) return 'text-profit';
    if (margin < 0) return 'text-loss';
    return 'text-neutral';
}

function rarityTier(item) {
    const id = String(item.product_id || '').toUpperCase();
    const name = String(item.name || '').toUpperCase();

    if (id.includes('SPECIAL') || name.includes('SPECIAL')) return 'special';
    if (id.includes('DIVINE') || name.includes('DIVINE')) return 'divine';
    if (id.includes('MYTHIC') || name.includes('MYTHIC')) return 'mythic';
    if (id.includes('LEGENDARY') || name.includes('LEGENDARY')) return 'legendary';
    if (id.includes('EPIC') || name.includes('EPIC')) return 'epic';
    if (id.includes('RARE') || name.includes('RARE')) return 'rare';
    if (id.includes('UNCOMMON') || name.includes('UNCOMMON')) return 'uncommon';
    return 'common';
}

function rarityColor(item) {
    return RARITY_COLORS[rarityTier(item)] || RARITY_COLORS.common;
}

function iconUrl(productId) {
    return `https://sky.shiiyu.moe/item/${encodeURIComponent(productId)}`;
}

function onIconError(productId) {
    failedIcons.value = {
        ...failedIcons.value,
        [productId]: true,
    };
}

const bestMarginItem = computed(() => {
    return [...liveItems.value].sort((a, b) => trueProfit(b) - trueProfit(a))[0] ?? null;
});

const highestVolumeItem = computed(() => {
    const totalVolume = (item) => Number(item.sell_volume || 0) + Number(item.buy_volume || 0);
    return [...liveItems.value].sort((a, b) => totalVolume(b) - totalVolume(a))[0] ?? null;
});

const bestNpcFlipItem = computed(() => {
    return [...liveItems.value]
        .filter((item) => isNpcProfit(item))
        .sort((a, b) => npcProfit(b) - npcProfit(a))[0] ?? null;
});

const bzToken = computed(() => {
    const cleaned = String(search.value || '')
        .trim()
        .replace(/\s+/g, '_')
        .replace(/[^A-Za-z0-9_:-]/g, '')
        .toUpperCase();

    if (cleaned.length > 0) {
        return cleaned;
    }

    return liveItems.value[0]?.product_id || '';
});

const bzPreviewCommand = computed(() => {
    if (!bzToken.value) return '/bz ';
    return `/bz ${bzToken.value}`;
});

async function copyQuickCommand() {
    const text = bzPreviewCommand.value;
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }
}

async function copyItemCommand(productId) {
    const text = `/bz ${String(productId || '').trim()}`;
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }
}
</script>

<template>
    <Head title="Bazaar Pro Dashboard" />

    <AuthenticatedLayout>
        <div class="py-5">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 grid grid-cols-1 gap-3 lg:grid-cols-3">
                    <section class="gold-card">
                        <p class="gold-card-title">Best Margin</p>
                        <p class="gold-card-main">{{ bestMarginItem?.name || 'No data' }}</p>
                        <p class="gold-card-sub">{{ bestMarginItem ? `${formatCoins(trueProfit(bestMarginItem))} coins` : '-' }}</p>
                    </section>
                    <section class="gold-card">
                        <p class="gold-card-title">Highest Volume</p>
                        <p class="gold-card-main">{{ highestVolumeItem?.name || 'No data' }}</p>
                        <p class="gold-card-sub">{{ highestVolumeItem ? `${formatCompact(Number(highestVolumeItem.sell_volume || 0) + Number(highestVolumeItem.buy_volume || 0))} 24h volume` : '-' }}</p>
                    </section>
                    <section class="gold-card">
                        <p class="gold-card-title">Most Profitable NPC Flip</p>
                        <p class="gold-card-main">{{ bestNpcFlipItem?.name || 'No profitable NPC flip' }}</p>
                        <p class="gold-card-sub">{{ bestNpcFlipItem ? `${formatCoins(npcProfit(bestNpcFlipItem))} NPC profit` : '-' }}</p>
                    </section>
                </div>

                <section class="mb-4 rounded-xl border border-border bg-surface-800/90 p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search item or paste item id..."
                            class="h-10 w-full max-w-md rounded-md border border-border bg-surface-900 px-3 text-sm text-white placeholder-neutral focus:border-border-light focus:outline-none"
                        >
                        <input
                            v-model.number="minDailyVolume"
                            type="number"
                            min="0"
                            step="1"
                            placeholder="Min Daily Volume"
                            class="h-10 w-44 rounded-md border border-border bg-surface-900 px-3 text-sm text-white placeholder-neutral focus:border-border-light focus:outline-none"
                        >
                        <div class="min-w-[260px] flex-1 rounded-md border border-border bg-surface-900 px-3 py-2 text-xs text-neutral">
                            <div class="text-[10px] uppercase tracking-wide text-white/70">/bz Quick Command</div>
                            <div class="mt-0.5 font-mono text-white">{{ bzPreviewCommand }}</div>
                        </div>
                        <button
                            type="button"
                            class="h-10 rounded-md border border-border bg-surface-700 px-3 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-surface-600"
                            @click="copyQuickCommand"
                        >
                            Quick Copy
                        </button>
                    </div>
                </section>

                <div class="overflow-x-auto rounded-xl border border-border">
                    <table class="w-full text-xs">
                        <thead class="sticky top-0 z-10 bg-surface-700/70 backdrop-blur-sm">
                            <tr class="uppercase tracking-wider text-neutral">
                                <th class="cursor-pointer select-none px-3 py-2 text-left" @click="toggleSort('name')">
                                    Item{{ sortIndicator('name') }}
                                </th>
                                <th class="cursor-pointer select-none px-3 py-2 text-right" @click="toggleSort('buy_price')">
                                    Buy Price{{ sortIndicator('buy_price') }}
                                </th>
                                <th class="cursor-pointer select-none px-3 py-2 text-right" @click="toggleSort('sell_price')">
                                    Sell Price{{ sortIndicator('sell_price') }}
                                </th>
                                <th class="cursor-pointer select-none px-3 py-2 text-right" @click="toggleSort('true_profit')">
                                    Margin{{ sortIndicator('true_profit') }}
                                </th>
                                <th class="cursor-pointer select-none px-3 py-2 text-right" @click="toggleSort('sell_volume')">
                                    Volume (24h S/B){{ sortIndicator('sell_volume') }}
                                </th>
                                <th class="cursor-pointer select-none px-3 py-2 text-right" @click="toggleSort('profit_score')">
                                    Flipping Score{{ sortIndicator('profit_score') }}
                                </th>
                                <th class="px-3 py-2 text-center">Quick Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in liveItems"
                                :key="item.id"
                                class="border-t border-border transition"
                                :class="[
                                    isNpcProfit(item) ? 'row-npc-profit' : 'hover:bg-white/[0.04]',
                                    isHotItem(item) ? 'row-hot' : '',
                                ]"
                            >
                                <td class="whitespace-nowrap px-3 py-2 font-medium">
                                    <div class="flex items-center gap-2">
                                        <img
                                            v-if="!failedIcons[item.product_id]"
                                            :src="iconUrl(item.product_id)"
                                            :alt="item.name"
                                            class="h-5 w-5 shrink-0"
                                            loading="lazy"
                                            @error="onIconError(item.product_id)"
                                        >
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <span :style="{ color: rarityColor(item) }">{{ item.name }}</span>
                                                <span v-if="isHotItem(item)" class="badge-hot">HOT</span>
                                                <span v-if="isNpcProfit(item)" class="badge-npc">NPC PROFIT</span>
                                            </div>
                                            <div class="text-[10px] text-neutral">{{ item.product_id }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-3 py-2 text-right font-mono text-rarity-legendary">
                                    {{ formatCoins(item.buy_price) }}
                                    <span class="ml-1" :class="trendClass(buyTrend[item.product_id] || 'flat')">{{ trendArrow(buyTrend[item.product_id] || 'flat') }}</span>
                                </td>

                                <td class="px-3 py-2 text-right font-mono text-rarity-legendary">
                                    {{ formatCoins(item.sell_price) }}
                                    <span class="ml-1" :class="trendClass(sellTrend[item.product_id] || 'flat')">{{ trendArrow(sellTrend[item.product_id] || 'flat') }}</span>
                                </td>

                                <td class="px-3 py-2 text-right font-mono" :class="marginClass(item)">
                                    <div>{{ formatCoins(trueProfit(item)) }}</div>
                                    <div class="text-[10px]">{{ marginPercent(item).toFixed(1) }}%</div>
                                </td>

                                <td class="px-3 py-2 text-right font-mono text-neutral">
                                    <div>{{ formatCompact(item.sell_volume) }} / {{ formatCompact(item.buy_volume) }}</div>
                                    <div class="text-[10px] text-white/80">{{ volumeRatio(item).toFixed(2) }}x</div>
                                </td>

                                <td class="px-3 py-2 text-right font-mono text-white">
                                    <div>{{ flippingScore(item).toFixed(1) }}</div>
                                    <div class="text-[10px] text-neutral">0-100</div>
                                </td>

                                <td class="px-3 py-2 text-center">
                                    <button
                                        type="button"
                                        class="rounded border border-border bg-surface-700 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-white transition hover:bg-surface-600"
                                        @click="copyItemCommand(item.product_id)"
                                    >
                                        Copy /bz
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="liveItems.length === 0">
                                <td colspan="7" class="px-3 py-8 text-center text-neutral">No items found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="items.links && items.links.length > 3" class="mt-4 flex items-center gap-1">
                    <template v-for="link in items.links" :key="link.label">
                        <button
                            v-if="link.url"
                            class="rounded border border-border px-2 py-1 text-xs"
                            :class="link.active ? 'bg-surface-500 text-white' : 'bg-surface-800 text-neutral hover:text-white'"
                            @click="router.get(link.url, {}, { preserveState: true, preserveScroll: true })"
                            v-html="link.label"
                        />
                        <span v-else class="px-2 py-1 text-xs text-neutral" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.gold-card {
    border: 1px solid rgba(245, 158, 11, 0.45);
    border-radius: 12px;
    padding: 12px;
    background:
        radial-gradient(circle at 80% 0%, rgba(251, 191, 36, 0.18), transparent 52%),
        linear-gradient(150deg, rgba(56, 36, 9, 0.7), rgba(23, 18, 8, 0.88));
    box-shadow: 0 8px 22px rgba(251, 191, 36, 0.12);
}

.gold-card-title {
    font-size: 11px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(253, 230, 138, 0.86);
}

.gold-card-main {
    margin-top: 6px;
    font-size: 16px;
    font-weight: 700;
    color: #ffffff;
}

.gold-card-sub {
    margin-top: 3px;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.85);
}

.row-npc-profit {
    background: linear-gradient(90deg, rgba(255, 215, 0, 0.06), rgba(255, 215, 0, 0.14));
}

.row-hot {
    animation: hotPulse 2.2s ease-in-out infinite;
}

.badge-hot {
    padding: 1px 5px;
    border-radius: 999px;
    border: 1px solid rgba(244, 114, 182, 0.48);
    background: rgba(244, 114, 182, 0.2);
    color: #fecdd3;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.badge-npc {
    padding: 1px 5px;
    border-radius: 999px;
    border: 1px solid rgba(250, 204, 21, 0.48);
    background: rgba(250, 204, 21, 0.25);
    color: #fef08a;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

@keyframes hotPulse {
    0% {
        filter: brightness(1);
    }
    50% {
        filter: brightness(1.08);
    }
    100% {
        filter: brightness(1);
    }
}
</style>
