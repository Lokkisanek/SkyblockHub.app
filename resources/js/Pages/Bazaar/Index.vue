<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import CopyCommandButton from '@/Components/CopyCommandButton.vue';

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

// Reactive copy of items data so we can mutate it from WebSocket events
const liveItems = ref([...props.items.data]);
const failedIcons = ref({});

// Keep liveItems in sync when Inertia navigates (pagination, sorting, search)
watch(() => props.items.data, (newData) => {
    liveItems.value = [...newData];
});

const search = ref(props.filters.search || '');
const sortBy = ref(props.filters.sort || 'profit_score');
const sortDir = ref(props.filters.dir || 'desc');
const minDailyVolume = ref(Number(props.filters.min_daily_volume ?? 1000));
const maxBuyPrice = ref(props.filters.max_buy_price ?? '');
const minTrueProfit = ref(props.filters.min_true_profit ?? '');
const minMarginPercent = ref(props.filters.min_margin_percent ?? '');

let debounceTimer = null;
let echoChannel = null;

watch([search, minDailyVolume, maxBuyPrice, minTrueProfit, minMarginPercent], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        applyFilters();
    }, 300);
});

// Listen for live bazaar updates via Reverb
onMounted(() => {
    if (window.Echo) {
        echoChannel = window.Echo.channel('bazaar');
        echoChannel.listen('.data.updated', (e) => {
            if (!e.items) return;
            // Update each item in the current view if it exists
            liveItems.value = liveItems.value.map(item => {
                const update = e.items[item.product_id];
                if (update) {
                    return {
                        ...item,
                        sell_price: update.sell_price,
                        buy_price: update.buy_price,
                        sell_volume: update.sell_volume,
                        buy_volume: update.buy_volume,
                        sell_orders: update.sell_orders,
                        buy_orders: update.buy_orders,
                        sell_moving_week: update.sell_moving_week,
                        buy_moving_week: update.buy_moving_week,
                        true_profit: trueProfit({
                            ...item,
                            sell_price: update.sell_price,
                            buy_price: update.buy_price,
                        }),
                        profit_score: profitScore({
                            ...item,
                            sell_price: update.sell_price,
                            buy_price: update.buy_price,
                            sell_volume: update.sell_volume,
                        }),
                    };
                }
                return item;
            });
        });
    }
});

onUnmounted(() => {
    if (echoChannel && window.Echo) {
        window.Echo.leave('bazaar');
    }
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

function findBestFlips() {
    sortBy.value = 'profit_score';
    sortDir.value = 'desc';
    applyFilters();
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
    return sortDir.value === 'asc' ? ' ▲' : ' ▼';
}

function formatNumber(n) {
    if (n === null || n === undefined) return '—';
    return Number(n).toLocaleString('en-US', { maximumFractionDigits: 1 });
}

function formatCoins(n) {
    if (n === null || n === undefined) return '—';
    return Number(n).toLocaleString('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
}

function trueProfit(item) {
    return (Number(item.sell_price) * 0.9875) - Number(item.buy_price);
}

function profitPercent(item) {
    const sell = Number(item.sell_price);
    if (sell <= 0) return 0;
    return ((trueProfit(item) / sell) * 100);
}

function marginClass(item) {
    const m = trueProfit(item);
    if (m > 0) return 'text-profit';
    if (m < 0) return 'text-loss';
    return 'text-neutral';
}

function profitScore(item) {
    return trueProfit(item) * Number(item.sell_volume || 0);
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
</script>

<template>
    <Head title="Bazaar Hub" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-sm font-semibold text-white uppercase tracking-wide">Bazaar Hub</h2>
        </template>

        <div class="py-4">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Top Controls -->
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search items…"
                        class="w-full max-w-xs bg-surface-800 border border-[#303030] rounded-none px-3 py-1.5 text-xs text-white placeholder-neutral focus:outline-none focus:border-border-light"
                    />
                    <input
                        v-model.number="minDailyVolume"
                        type="number"
                        min="0"
                        step="1"
                        placeholder="Min Daily Volume"
                        class="w-40 bg-surface-800 border border-[#303030] rounded-none px-3 py-1.5 text-xs text-white placeholder-neutral focus:outline-none focus:border-border-light"
                    />
                    <input
                        v-model="maxBuyPrice"
                        type="number"
                        min="0"
                        step="0.1"
                        placeholder="Max Buy Price"
                        class="w-40 bg-surface-800 border border-[#303030] rounded-none px-3 py-1.5 text-xs text-white placeholder-neutral focus:outline-none focus:border-border-light"
                    />
                    <input
                        v-model="minTrueProfit"
                        type="number"
                        step="0.1"
                        placeholder="Min True Profit"
                        class="w-40 bg-surface-800 border border-[#303030] rounded-none px-3 py-1.5 text-xs text-white placeholder-neutral focus:outline-none focus:border-border-light"
                    />
                    <input
                        v-model="minMarginPercent"
                        type="number"
                        step="0.1"
                        placeholder="Min Margin %"
                        class="w-36 bg-surface-800 border border-[#303030] rounded-none px-3 py-1.5 text-xs text-white placeholder-neutral focus:outline-none focus:border-border-light"
                    />
                    <button
                        type="button"
                        @click="findBestFlips"
                        class="h-[30px] px-3 text-xs font-semibold uppercase tracking-wide bg-surface-700 border border-[#303030] text-white hover:text-rarity-uncommon"
                    >
                        Best Flips
                    </button>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto border border-border">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-surface-700 text-neutral uppercase tracking-wider">
                                <th class="px-3 py-2 text-left border-b border-border cursor-pointer select-none" @click="toggleSort('name')">
                                    Item{{ sortIndicator('name') }}
                                </th>
                                <th class="px-3 py-2 text-right border-b border-border cursor-pointer select-none" @click="toggleSort('buy_price')">
                                    Buy Price{{ sortIndicator('buy_price') }}
                                </th>
                                <th class="px-3 py-2 text-right border-b border-border cursor-pointer select-none" @click="toggleSort('sell_price')">
                                    Sell Price{{ sortIndicator('sell_price') }}
                                </th>
                                <th class="px-3 py-2 text-right border-b border-border cursor-pointer select-none" @click="toggleSort('true_profit')">
                                    True Profit (Taxed){{ sortIndicator('true_profit') }}
                                </th>
                                <th class="px-3 py-2 text-right border-b border-border cursor-pointer select-none" @click="toggleSort('margin_percent')">
                                    Margin %{{ sortIndicator('margin_percent') }}
                                </th>
                                <th class="px-3 py-2 text-right border-b border-border cursor-pointer select-none" @click="toggleSort('buy_volume')">
                                    Buy Vol{{ sortIndicator('buy_volume') }}
                                </th>
                                <th class="px-3 py-2 text-right border-b border-border cursor-pointer select-none" @click="toggleSort('sell_volume')">
                                    Sell Vol{{ sortIndicator('sell_volume') }}
                                </th>
                                <th class="px-3 py-2 text-right border-b border-border cursor-pointer select-none" @click="toggleSort('buy_volume')">
                                    Buy/wk{{ sortIndicator('buy_volume') }}
                                </th>
                                <th class="px-3 py-2 text-center border-b border-border">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in liveItems"
                                :key="item.id"
                                class="border-b border-border hover:bg-surface-700"
                            >
                                <td class="px-3 py-1.5 font-medium whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <img
                                            v-if="!failedIcons[item.product_id]"
                                            :src="iconUrl(item.product_id)"
                                            :alt="item.name"
                                            class="h-4 w-4 shrink-0"
                                            loading="lazy"
                                            @error="onIconError(item.product_id)"
                                        >
                                        <span :style="{ color: rarityColor(item) }">{{ item.name }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-1.5 text-right text-rarity-legendary font-mono">
                                    {{ formatCoins(item.buy_price) }}
                                </td>
                                <td class="px-3 py-1.5 text-right text-rarity-legendary font-mono">
                                    {{ formatCoins(item.sell_price) }}
                                </td>
                                <td class="px-3 py-1.5 text-right font-mono" :class="marginClass(item)">
                                    {{ formatCoins(trueProfit(item)) }}
                                </td>
                                <td class="px-3 py-1.5 text-right font-mono" :class="marginClass(item)">
                                    {{ profitPercent(item).toFixed(1) }}%
                                </td>
                                <td class="px-3 py-1.5 text-right text-neutral font-mono">
                                    {{ formatNumber(item.buy_volume) }}
                                </td>
                                <td class="px-3 py-1.5 text-right text-neutral font-mono">
                                    {{ formatNumber(item.sell_volume) }}
                                </td>
                                <td class="px-3 py-1.5 text-right text-neutral font-mono">
                                    {{ formatNumber(item.buy_moving_week) }}
                                </td>
                                <td class="px-3 py-1.5 text-center">
                                    <CopyCommandButton :product-id="item.product_id" />
                                </td>
                            </tr>
                            <tr v-if="liveItems.length === 0">
                                <td colspan="9" class="px-3 py-6 text-center text-neutral">
                                    No items found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="items.links && items.links.length > 3" class="mt-3 flex items-center gap-1">
                    <template v-for="link in items.links" :key="link.label">
                        <button
                            v-if="link.url"
                            @click="router.get(link.url, {}, { preserveState: true, preserveScroll: true })"
                            class="px-2 py-1 text-xs border border-border"
                            :class="link.active ? 'bg-surface-500 text-white' : 'bg-surface-800 text-neutral hover:text-white'"
                            v-html="link.label"
                        />
                        <span v-else class="px-2 py-1 text-xs text-neutral" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
