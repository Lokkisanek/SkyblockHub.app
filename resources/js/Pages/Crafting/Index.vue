<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ContextualUpgradePrompt from '@/Components/ContextualUpgradePrompt.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    recipes: Array,
    categories: Array,
});

const search            = ref('');
const categoryFilter    = ref('');
const sortKey           = ref('net_profit');
const sortDir           = ref('desc');
const expandedRecipe    = ref(null);
const hideUnprofitable  = ref(false);
const failedIcons       = ref(new Set());

function toggleExpand(id) {
    expandedRecipe.value = expandedRecipe.value === id ? null : id;
}

function onIconError(id) {
    failedIcons.value = new Set([...failedIcons.value, id]);
}

// ── Rarity ───────────────────────────────────────────────────────────────────
const RARITY_COLORS = {
    divine:    '#55FFFF',
    mythic:    '#FF55FF',
    legendary: '#FFAA00',
    epic:      '#AA00AA',
    rare:      '#5555FF',
    uncommon:  '#55FF55',
    common:    '#FFFFFF',
};

function rarityTier(item) {
    const id   = (item.product_id || '').toLowerCase();
    const name = (item.name       || '').toLowerCase();
    if (id.includes('divine')   || name.includes('divine'))    return 'divine';
    if (id.includes('mythic')   || name.includes('mythic'))    return 'mythic';
    if (/enchantment_ultimate_/.test(id))                      return 'legendary';
    if (id.includes('legendary')|| name.includes('legendary')) return 'legendary';
    if (id.includes('epic')     || name.includes('epic'))      return 'epic';
    if (id.includes('rare')     || name.includes('rare'))      return 'rare';
    if (id.includes('uncommon') || name.includes('uncommon'))  return 'uncommon';
    return 'common';
}

function rarityColor(item) {
    return RARITY_COLORS[rarityTier(item)];
}

// ── Computed ──────────────────────────────────────────────────────────────────
const totalRecipes    = computed(() => props.recipes.length);
const profitableCount = computed(() => props.recipes.filter(r => r.net_profit > 0).length);
const bestMargin      = computed(() => {
    const pos = props.recipes.filter(r => r.margin_percent > 0);
    return pos.length ? Math.max(...pos.map(r => r.margin_percent)) : null;
});

const filteredRecipes = computed(() => {
    let items = [...props.recipes];

    if (search.value) {
        const q = search.value.toLowerCase();
        items = items.filter(r => (r.name || '').toLowerCase().includes(q));
    }

    if (categoryFilter.value) {
        items = items.filter(r => r.category === categoryFilter.value);
    }

    if (hideUnprofitable.value) {
        items = items.filter(r => r.net_profit > 0);
    }

    items.sort((a, b) => {
        let av = a[sortKey.value] ?? 0;
        let bv = b[sortKey.value] ?? 0;
        if (typeof av === 'string') av = av.toLowerCase();
        if (typeof bv === 'string') bv = bv.toLowerCase();
        if (av < bv) return sortDir.value === 'asc' ? -1 : 1;
        if (av > bv) return sortDir.value === 'asc' ? 1 : -1;
        return 0;
    });

    return items;
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function toggleSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = key === 'name' ? 'asc' : 'desc';
    }
}

function sortIcon(key) {
    if (sortKey.value !== key) return '';
    return sortDir.value === 'asc' ? '▲' : '▼';
}

function fmt(n) {
    if (n === null || n === undefined) return '—';
    return Number(n).toLocaleString('en-US', { maximumFractionDigits: 1 });
}

function pnlStyle(v) {
    if (v > 0) return { color: '#55FF55' };
    if (v < 0) return { color: '#FF5555' };
    return { color: '#AAAAAA' };
}

// ── Auto-refresh on price updates ─────────────────────────────────────────────
let echoChannel = null;
let pollInterval = null;

async function refreshRecipes() {
    try {
        const response = await fetch('/api/arbitrage/crafting');
        const data = await response.json();

        // Update recipes data
        props.recipes.length = 0;
        props.recipes.push(...data);
    } catch (error) {
        console.error('Failed to refresh crafting recipes:', error);
    }
}

onMounted(() => {
    // Listen to Bazaar price updates via WebSocket
    if (window.Echo) {
        echoChannel = window.Echo.channel('bazaar');
        echoChannel.listen('data.updated', () => {
            refreshRecipes();
        });
    }

    // Fallback: Poll every 30 seconds if WebSocket isn't available or as backup
    pollInterval = setInterval(() => {
        refreshRecipes();
    }, 30000);
});

onUnmounted(() => {
    // Cleanup WebSocket listener
    if (echoChannel && window.Echo) {
        window.Echo.leave('bazaar');
    }

    // Cleanup polling interval
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});
</script>

<template>
    <Head :title="t('crafting.title')" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
                <div class="bg-surface-800 border border-border p-3">
                    <div class="text-[10px] uppercase text-neutral tracking-wider">{{ t('crafting.totalRecipes') }}</div>
                    <div class="text-base font-semibold text-white mt-1">{{ totalRecipes }}</div>
                </div>
                <div class="bg-surface-800 border border-border p-3">
                    <div class="text-[10px] uppercase text-neutral tracking-wider">{{ t('crafting.profitable') }}</div>
                    <div class="text-base font-semibold mt-1" style="color:#55FF55">{{ profitableCount }}</div>
                </div>
                <div class="bg-surface-800 border border-border p-3 hidden sm:block">
                    <div class="text-[10px] uppercase text-neutral tracking-wider">{{ t('crafting.bestMargin') }}</div>
                    <div class="text-base font-semibold mt-1"
                         :style="bestMargin !== null ? { color: '#55FF55' } : { color: '#AAAAAA' }">
                        {{ bestMargin !== null ? fmt(bestMargin) + '%' : '—' }}
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <ContextualUpgradePrompt
                    module-key="crafting"
                    experiment-key="crafting-upgrade-copy"
                    target-tier="mvp"
                    :kicker="t('billing.readyUpgrade')"
                    :free-title="t('featurePrompts.crafting.freeTitle')"
                    :free-title-b="t('featurePrompts.crafting.freeTitleB')"
                    :free-body="t('featurePrompts.crafting.freeBody')"
                    :free-body-b="t('featurePrompts.crafting.freeBodyB')"
                    :premium-title="t('featurePrompts.crafting.premiumTitle')"
                    :premium-title-b="t('featurePrompts.crafting.premiumTitleB')"
                    :premium-body="t('featurePrompts.crafting.premiumBody')"
                    :premium-body-b="t('featurePrompts.crafting.premiumBodyB')"
                    :cta-label="t('billing.upgradeLink')"
                    :compare-label="t('pricingFaq.comparePlans')"
                />
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <input
                    v-model="search"
                    type="text"
                    :placeholder="t('crafting.searchPlaceholder')"
                    class="bg-surface-800 border border-border text-xs text-white px-3 py-1.5 placeholder-neutral focus:outline-none focus:border-border-light w-48"
                />
                <select
                    v-model="categoryFilter"
                    class="bg-surface-800 border border-border text-xs text-white px-3 py-1.5 focus:outline-none focus:border-border-light"
                >
                    <option value="">{{ t('crafting.allCategories') }}</option>
                    <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                </select>
                <label class="flex items-center gap-2 text-xs text-neutral cursor-pointer select-none">
                    <input
                        v-model="hideUnprofitable"
                        type="checkbox"
                        class="accent-profit w-3.5 h-3.5"
                    />
                    {{ t('crafting.hideUnprofitable') }}
                </label>
            </div>

            <!-- Recipes Table -->
            <div class="bg-surface-800 border border-border overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-border text-[10px] uppercase text-neutral tracking-wider">
                            <th class="text-left px-3 py-2 cursor-pointer hover:text-white" @click="toggleSort('name')">
                                {{ t('crafting.result') }} {{ sortIcon('name') }}
                            </th>
                            <th class="text-left px-3 py-2">{{ t('crafting.category') }}</th>
                            <th class="text-right px-3 py-2 cursor-pointer hover:text-white" @click="toggleSort('craft_cost')">
                                {{ t('crafting.craftCost') }} {{ sortIcon('craft_cost') }}
                            </th>
                            <th class="text-right px-3 py-2 cursor-pointer hover:text-white" @click="toggleSort('sell_price')">
                                {{ t('crafting.sellPrice') }} {{ sortIcon('sell_price') }}
                            </th>
                            <th class="text-right px-3 py-2 cursor-pointer hover:text-white" @click="toggleSort('net_profit')">
                                {{ t('crafting.profitTaxed') }} {{ sortIcon('net_profit') }}
                            </th>
                            <th class="text-right px-3 py-2 cursor-pointer hover:text-white" @click="toggleSort('margin_percent')">
                                {{ t('common.margin') }} {{ sortIcon('margin_percent') }}
                            </th>
                            <th class="text-center px-3 py-2 w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="recipe in filteredRecipes" :key="recipe.product_id">
                            <!-- Main row -->
                            <tr
                                class="border-b border-border/50 hover:bg-surface-700 cursor-pointer"
                                @click="toggleExpand(recipe.product_id)"
                            >
                                <td class="px-3 py-2 whitespace-nowrap font-medium">
                                    <div class="flex items-center gap-1.5">
                                        <img
                                            v-if="!failedIcons.has(recipe.product_id)"
                                            :src="`https://sky.coflnet.com/static/icon/${recipe.product_id}`"
                                            :alt="recipe.name"
                                            class="w-4 h-4 flex-shrink-0"
                                            @error="onIconError(recipe.product_id)"
                                        />
                                        <span :style="{ color: rarityColor(recipe) }">{{ recipe.name }}</span>
                                        <span v-if="recipe.output_quantity > 1" class="text-neutral ml-1">×{{ recipe.output_quantity }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-neutral">{{ recipe.category || '—' }}</td>
                                <td class="px-3 py-2 text-right text-neutral">{{ fmt(recipe.craft_cost) }}</td>
                                <td class="px-3 py-2 text-right text-white">{{ fmt(recipe.sell_price) }}</td>
                                <td class="px-3 py-2 text-right font-medium" :style="pnlStyle(recipe.net_profit)">
                                    {{ recipe.net_profit >= 0 ? '+' : '' }}{{ fmt(recipe.net_profit) }}
                                </td>
                                <td class="px-3 py-2 text-right" :style="pnlStyle(recipe.margin_percent)">
                                    {{ recipe.margin_percent >= 0 ? '+' : '' }}{{ recipe.margin_percent.toFixed(1) }}%
                                </td>
                                <td class="px-3 py-2 text-center text-neutral">
                                    <span class="text-[10px]">{{ expandedRecipe === recipe.product_id ? '▼' : '▶' }}</span>
                                </td>
                            </tr>

                            <!-- Ingredient breakdown -->
                            <tr v-if="expandedRecipe === recipe.product_id">
                                <td colspan="7" class="p-0">
                                    <div class="bg-surface-900 border-t border-border px-4 py-3">
                                        <div class="text-[10px] uppercase text-neutral tracking-wider mb-2">{{ t('crafting.ingredients') }}</div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                            <div
                                                v-for="(ing, idx) in recipe.ingredients"
                                                :key="idx"
                                                class="border border-border bg-surface-800 px-3 py-2 flex items-center gap-2"
                                            >
                                                <img
                                                    v-if="!failedIcons.has(ing.item_id)"
                                                    :src="`https://sky.coflnet.com/static/icon/${ing.item_id}`"
                                                    :alt="ing.name"
                                                    class="w-4 h-4 flex-shrink-0"
                                                    @error="onIconError(ing.item_id)"
                                                />
                                                <div class="flex-1 flex items-center justify-between">
                                                    <div>
                                                        <span class="text-white">{{ ing.name }}</span>
                                                        <span class="text-neutral text-[10px] ml-1">×{{ ing.quantity }}</span>
                                                    </div>
                                                    <div class="text-right ml-2">
                                                        <div class="text-[10px] text-neutral">{{ fmt(ing.unit_price) }}{{ t('crafting.perEach') }}</div>
                                                        <div class="text-white font-medium">{{ fmt(ing.total_cost) }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="!recipe.all_available" class="mt-2 text-[10px]" style="color:#FF5555">
                                            {{ t('crafting.ingredientWarning') }}
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <tr v-if="!filteredRecipes.length">
                            <td colspan="7" class="px-3 py-6 text-center text-neutral">
                                {{ totalRecipes === 0
                                    ? t('crafting.noRecipesLoaded')
                                    : t('crafting.noRecipesMatch') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

