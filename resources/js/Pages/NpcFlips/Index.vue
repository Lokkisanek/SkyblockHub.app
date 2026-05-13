<template>
  <AuthenticatedLayout>
    <Head :title="t('npc.title')" />

    <div class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
      <div class="flex flex-col gap-4 border-b border-white/[0.06] pb-5 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0 space-y-1.5">
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-text-tertiary">{{ t('nav.npcFlips') }}</p>
          <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ t('npc.heading') }}</h1>
          <p class="text-sm leading-relaxed text-text-secondary">{{ t('npc.subtitle') }}</p>
          <p class="text-xs leading-relaxed text-text-tertiary">
            {{ t('npc.activeBuyTax', { rate: ((tax_meta?.rate ?? 0.01) * 100).toFixed(2), source: tax_meta?.source || 'default' }) }}
            <template v-if="tax_meta?.instant_sell_tax_rate != null">
              · {{ t('npc.sellTaxContext', { rate: (Number(tax_meta.instant_sell_tax_rate) * 100).toFixed(2) }) }}
            </template>
          </p>
        </div>

        <div class="flex shrink-0 flex-col gap-2 sm:items-end">
          <button
            type="button"
            class="rounded-xl border border-border/80 bg-surface-800/80 px-4 py-2 text-sm font-semibold text-text-primary transition hover:border-profit/35 hover:bg-profit/10 hover:text-white"
            @click="refreshMarket"
          >
            {{ t('bazaar.refreshPrices') }}
          </button>
          <span class="text-center text-[11px] text-text-tertiary sm:text-right">
            {{ t('bazaar.autoRefreshHint', { time: formatCountdown(autoRefreshRemainingSeconds) }) }}
          </span>
        </div>
      </div>

      <div :class="pageSurface" class="p-4 sm:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:gap-4">
          <div class="min-w-0 flex-1">
            <label class="block text-xs font-medium text-text-secondary">{{ t('common.search') }}</label>
            <input
              v-model="search"
              type="text"
              :placeholder="t('npc.itemPlaceholder')"
              :class="fieldInputClass"
              @input="debouncedApplyFilters"
            />
          </div>

          <div class="w-full shrink-0 lg:w-52">
            <label class="block text-xs font-medium text-text-secondary">{{ t('bazaar.category') }}</label>
            <select
              v-model="category"
              :class="fieldInputClass"
              @change="applyFilters"
            >
              <option value="">{{ t('bazaar.allCategories') }}</option>
              <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>

          <details class="w-full list-none rounded-xl border border-border/60 bg-surface-800/50 p-3 lg:max-w-lg [&::-webkit-details-marker]:hidden">
            <summary class="cursor-pointer select-none text-sm font-semibold text-text-primary transition hover:text-white">
              {{ t('bazaar.sortAndFilters') }}
            </summary>
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <label class="block text-xs font-medium text-text-secondary">{{ t('common.sortBy') }}</label>
                <select
                  v-model="sortBy"
                  :class="fieldInputClass"
                  @change="applyFilters"
                >
                  <option value="best_pick_score">{{ t('npc.bestPickScore') }}</option>
                  <option value="profit_per_item">{{ t('npc.profitPerItem') }}</option>
                  <option value="profit_per_inventory">{{ t('npc.profitPerInventory') }}</option>
                  <option value="coins_per_hour">{{ t('common.coinsPerHour') }}</option>
                  <option value="time_to_fill_minutes">{{ t('npc.timeToFill') }}</option>
                  <option value="max_profit">{{ t('npc.maxProfit') }}</option>
                  <option value="hours_before_limited">{{ t('npc.duration') }}</option>
                  <option value="name">{{ t('common.name') }}</option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-medium text-text-secondary">{{ t('common.direction') }}</label>
                <select
                  v-model="sortDir"
                  :class="fieldInputClass"
                  @change="applyFilters"
                >
                  <option value="desc">{{ t('common.descending') }}</option>
                  <option value="asc">{{ t('common.ascending') }}</option>
                </select>
              </div>

              <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-text-secondary">{{ t('npc.minCoinsPerHour') }}</label>
                <input
                  v-model.number="minCoinsPerHour"
                  type="number"
                  min="0"
                  step="10000"
                  :class="fieldInputClass"
                  :placeholder="t('bazaar.optional')"
                  @change="applyFilters"
                />
              </div>

              <div class="flex flex-col gap-2 sm:col-span-2">
                <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-text-primary">
                  <input v-model="hasCompactor" type="checkbox" class="rounded border-border/80 text-profit focus:ring-profit/40" @change="applyFilters" />
                  <span>{{ t('npc.compactorLabel') }}</span>
                </label>
                <p class="text-[10px] text-text-tertiary">{{ t('npc.compactorHint') }}</p>
              </div>

              <div class="flex items-end sm:col-span-2">
                <button
                  type="button"
                  class="rounded-xl border border-border/80 bg-surface-800/80 px-4 py-2 text-sm font-medium text-text-primary transition hover:bg-white/[0.06]"
                  @click="resetFilters"
                >
                  {{ t('common.reset') }}
                </button>
              </div>
            </div>
          </details>
        </div>
      </div>

      <div v-if="best_picks" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div :class="[pageSurface, 'p-4 text-center']">
          <h3 class="text-[11px] font-semibold uppercase tracking-[0.2em] text-text-tertiary">{{ t('npc.bestOverall') }}</h3>
          <template v-if="best_picks.overall?.[0]">
            <div class="mt-3 flex flex-col items-center">
              <img
                :src="getFlipTextureUrl(best_picks.overall[0])"
                :data-fallback="getFlipTextureFallbackUrl(best_picks.overall[0])"
                :alt="best_picks.overall[0].name"
                class="h-12 w-12 object-contain"
                loading="lazy"
                @error="handleTextureError"
              />
              <div class="mt-2 line-clamp-2 font-semibold text-white">{{ best_picks.overall[0].name }}</div>
              <div class="text-sm text-positive">{{ formatCoins(best_picks.overall[0].profit_per_item) }}{{ t('npc.perItem') }}</div>
              <div class="text-[11px] text-text-tertiary">{{ t('npc.score', { score: best_picks.overall[0].best_pick_score.toFixed(1) }) }}</div>
            </div>
          </template>
          <template v-else>
            <div class="mt-3 text-sm text-text-secondary">{{ t('common.noData') }}</div>
          </template>
        </div>

        <div :class="[pageSurface, 'p-4 text-center']">
          <h3 class="text-[11px] font-semibold uppercase tracking-[0.2em] text-text-tertiary">{{ t('npc.bestStackable') }}</h3>
          <template v-if="best_picks.stackable?.[0]">
            <div class="mt-3 flex flex-col items-center">
              <img
                :src="getFlipTextureUrl(best_picks.stackable[0])"
                :data-fallback="getFlipTextureFallbackUrl(best_picks.stackable[0])"
                :alt="best_picks.stackable[0].name"
                class="h-12 w-12 object-contain"
                loading="lazy"
                @error="handleTextureError"
              />
              <div class="mt-2 line-clamp-2 font-semibold text-white">{{ best_picks.stackable[0].name }}</div>
              <div class="text-sm text-positive">{{ formatCoins(best_picks.stackable[0].coins_per_hour) }}{{ t('npc.perHour') }}</div>
            </div>
          </template>
          <template v-else>
            <div class="mt-3 text-sm text-text-secondary">{{ t('common.noData') }}</div>
          </template>
        </div>

        <div :class="[pageSurface, 'p-4 text-center']">
          <h3 class="text-[11px] font-semibold uppercase tracking-[0.2em] text-text-tertiary">{{ t('npc.highestThroughput') }}</h3>
          <template v-if="best_picks.hourly_profit?.[0]">
            <div class="mt-3 flex flex-col items-center">
              <img
                :src="getFlipTextureUrl(best_picks.hourly_profit[0])"
                :data-fallback="getFlipTextureFallbackUrl(best_picks.hourly_profit[0])"
                :alt="best_picks.hourly_profit[0].name"
                class="h-12 w-12 object-contain"
                loading="lazy"
                @error="handleTextureError"
              />
              <div class="mt-2 line-clamp-2 font-semibold text-white">{{ best_picks.hourly_profit[0].name }}</div>
              <div class="text-sm text-positive">{{ formatCoins(best_picks.hourly_profit[0].coins_per_hour) }}{{ t('npc.perHour') }}</div>
            </div>
          </template>
          <template v-else>
            <div class="mt-3 text-sm text-text-secondary">{{ t('common.noData') }}</div>
          </template>
        </div>
      </div>

      <div :class="[pageSurface, 'overflow-hidden']">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="sr-only">
              <tr>
                <th scope="col">{{ t('common.item') }}</th>
                <th scope="col">{{ t('npc.stack') }}</th>
                <th scope="col">{{ t('npc.bestScore') }}</th>
                <th scope="col">{{ t('npc.bzBuy') }}</th>
                <th scope="col">{{ t('npc.npcSell') }}</th>
                <th scope="col">{{ t('npc.profitPerItem') }}</th>
                <th scope="col">{{ t('common.volume1h') }}</th>
                <th scope="col">{{ t('common.coinsPerHour') }}</th>
                <th scope="col">{{ t('npc.profitPerInventory') }}</th>
                <th scope="col">{{ t('npc.timeToFill') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border/50">
              <tr
                v-for="(flip, index) in flips"
                :key="flip.product_id"
                class="relative transition hover:bg-white/[0.03]"
                :class="[rowClass(flip), isRestrictedFlip(index) ? 'flip-row-restricted' : '']"
              >
                <td class="px-4 py-3 font-semibold text-white">
                  <div class="flex items-center gap-2">
                    <img
                      :src="getFlipTextureUrl(flip)"
                      :data-fallback="getFlipTextureFallbackUrl(flip)"
                      :alt="flip.name"
                      class="h-10 w-10 shrink-0 object-contain"
                      loading="lazy"
                      @error="handleTextureError"
                    />
                    <span>{{ flip.name }}</span>
                    <span v-if="flip.can_compact" class="rounded bg-info/20 px-1.5 py-0.5 text-[10px] font-semibold text-info">{{ t('npc.pcBadge') }}</span>
                    <span v-if="flip.is_ultimate_flip" class="rounded bg-warning/20 px-1.5 py-0.5 text-[10px] font-semibold text-warning">{{ t('npc.ultimateFlip') }}</span>
                  </div>
                  <div v-if="flip.can_compact && flip.compactor_target_id" class="text-[11px] text-text-secondary">
                    {{ t('npc.compactsTo', { target: flip.compactor_target_id, ratio: flip.compactor_ratio.toFixed(0) }) }}
                  </div>
                </td>

                <td class="px-4 py-3 text-center" :aria-label="t('npc.stack')">
                  <span
                    class="inline-block rounded px-2 py-1 text-xs font-semibold"
                    :class="flip.is_stackable ? 'bg-info/20 text-info' : 'bg-surface-600 text-text-secondary'"
                  >
                    {{ flip.is_stackable ? t('common.yes') : t('common.no') }}
                  </span>
                </td>

                <td class="px-4 py-3 text-right font-semibold text-white">{{ flip.best_pick_score.toFixed(1) }}</td>
                <td class="px-4 py-3 text-right text-text-secondary" :aria-label="t('npc.bzBuy')">{{ formatCoins(flip.buy_price) }}</td>
                <td class="px-4 py-3 text-right font-semibold text-white" :aria-label="t('npc.npcSell')">{{ formatCoins(flip.npc_sell_price) }}</td>

                <td class="px-4 py-3 text-right">
                  <div class="font-semibold" :class="profitClass(flip.profit_percent)">
                    {{ formatCoins(flip.profit_per_item) }}
                  </div>
                  <div class="text-xs" :class="profitClass(flip.profit_percent)">
                    {{ flip.profit_percent.toFixed(1) }}%
                  </div>
                  <div v-if="flip.profit_percent < 2" class="text-xs text-red-400">{{ t('npc.tooCloseToNpc') }}</div>
                </td>

                <td class="px-4 py-3 text-right text-text-secondary">{{ formatCompact(flip.one_hour_instasells) }}</td>
                <td class="px-4 py-3 text-right font-semibold text-white">{{ formatCoins(flip.coins_per_hour) }}</td>
                <td class="px-4 py-3 text-right text-white">{{ formatCoins(flip.profit_per_inventory) }}</td>
                <td class="px-4 py-3 text-right text-text-secondary">{{ formatMinutes(flip.time_to_fill_minutes) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="flips.length === 0" class="px-4 py-10 text-center text-sm text-text-secondary">
          {{ t('npc.noFlips') }}
        </div>

        <div v-else class="border-t border-border/60 px-4 py-2.5 text-[11px] leading-relaxed text-text-tertiary">
          {{ t('npc.tableFootnote') }}
        </div>
      </div>

      <div v-if="pagination.last_page > 1" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="text-sm text-text-secondary">
          {{ t('common.page') }} {{ pagination.current_page }} {{ t('common.of') }} {{ pagination.last_page }} ({{ pagination.total }} {{ t('common.total') }})
        </div>
        <div class="flex flex-wrap gap-2">
          <Link
            v-if="pagination.current_page > 1"
            :href="buildPaginationUrl(1)"
            class="rounded-xl border border-border/80 bg-surface-900/75 px-3 py-2 text-sm text-text-primary shadow-[0_8px_24px_rgba(0,0,0,0.2)] backdrop-blur-sm transition hover:border-profit/30 hover:bg-profit/10 hover:text-white"
          >
            {{ t('common.first') }}
          </Link>
          <Link
            v-if="pagination.current_page > 1"
            :href="buildPaginationUrl(pagination.current_page - 1)"
            class="rounded-xl border border-border/80 bg-surface-900/75 px-3 py-2 text-sm text-text-primary shadow-[0_8px_24px_rgba(0,0,0,0.2)] backdrop-blur-sm transition hover:border-profit/30 hover:bg-profit/10 hover:text-white"
          >
            {{ t('common.previous') }}
          </Link>
          <Link
            v-if="pagination.current_page < pagination.last_page"
            :href="buildPaginationUrl(pagination.current_page + 1)"
            class="rounded-xl border border-border/80 bg-surface-900/75 px-3 py-2 text-sm text-text-primary shadow-[0_8px_24px_rgba(0,0,0,0.2)] backdrop-blur-sm transition hover:border-profit/30 hover:bg-profit/10 hover:text-white"
          >
            {{ t('common.next') }}
          </Link>
          <Link
            v-if="pagination.current_page < pagination.last_page"
            :href="buildPaginationUrl(pagination.last_page)"
            class="rounded-xl border border-border/80 bg-surface-900/75 px-3 py-2 text-sm text-text-primary shadow-[0_8px_24px_rgba(0,0,0,0.2)] backdrop-blur-sm transition hover:border-profit/30 hover:bg-profit/10 hover:text-white"
          >
            {{ t('common.last') }}
          </Link>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { getItemTextureUrl, preloadAllTextures } from '@/utils/textures'
import { useI18n } from '@/strings/useI18n'

const { t } = useI18n()

/** Match Bazaar page glass panels */
const pageSurface =
  'rounded-2xl border border-border/80 bg-surface-900/75 shadow-[0_16px_40px_rgba(0,0,0,0.35)] backdrop-blur-sm'

const fieldInputClass =
  'mt-1 w-full rounded-xl border border-border/80 bg-surface-800/80 px-3 py-2 text-sm text-text-primary placeholder:text-text-tertiary transition focus:border-profit/55 focus:outline-none focus:ring-2 focus:ring-profit/15'

const props = defineProps({
  flips: Array,
  pagination: Object,
  filters: Object,
  best_picks: Object,
  tax_meta: Object,
  has_compactor: Boolean,
  categories: {
    type: Array,
    default: () => [],
  },
})

const isRestrictedFlip = () => false

const search = ref(props.filters.search || '')
const category = ref(props.filters.category || '')
const sortBy = ref(props.filters.sort || 'best_pick_score')
const sortDir = ref(props.filters.dir || 'desc')
const minCoinsPerHour = ref(props.filters.min_coins_per_hour ?? '')
const hasCompactor = ref(!!props.has_compactor)
const isRefreshing = ref(false)

const AUTO_REFRESH_INTERVAL_SECONDS = 180
const autoRefreshRemainingSeconds = ref(AUTO_REFRESH_INTERVAL_SECONDS)

let debounceTimer = null
let autoRefreshTickTimer = null
const DEFAULT_ITEM_TEXTURE = '/img/textures/chest.png'
const COFLNET_ICON_BASE = 'https://sky.coflnet.com/static/icon/'

const PRODUCT_TEXTURE_OVERRIDES = {
  ICE_BAIT: '/item/fish_cod_raw',
  LUSHILAC: '/item/double_plant',
  SNOW_BALL: '/item/snowball',
  SUGAR_CANE: '/item/sugar_cane',
  WILD_ROSE: '/item/red_flower',
  SEEDS: '/item/wheat_seeds',
  PRISMARINE_SHARD: '/item/prismarine_shard',
}

watch(() => props.filters, (f) => {
  search.value = f.search || ''
  category.value = f.category || ''
  sortBy.value = f.sort || 'best_pick_score'
  sortDir.value = f.dir || 'desc'
  minCoinsPerHour.value = f.min_coins_per_hour ?? ''
}, { deep: true })

watch(() => props.has_compactor, (v) => {
  hasCompactor.value = !!v
})

function filterPayload(extra = {}) {
  const raw = {
    search: search.value || undefined,
    category: category.value || undefined,
    sort: sortBy.value,
    dir: sortDir.value,
    min_coins_per_hour: minCoinsPerHour.value === '' || minCoinsPerHour.value === null ? undefined : minCoinsPerHour.value,
    has_compactor: hasCompactor.value ? 1 : undefined,
    ...extra,
  }

  return Object.fromEntries(Object.entries(raw).filter(([, v]) => v !== undefined && v !== ''))
}

function debouncedApplyFilters() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 300)
}

function applyFilters() {
  router.get(route('npc-flips'), filterPayload(), {
    preserveState: true,
    preserveScroll: true,
  })
}

function resetFilters() {
  search.value = ''
  category.value = ''
  sortBy.value = 'best_pick_score'
  sortDir.value = 'desc'
  minCoinsPerHour.value = ''
  hasCompactor.value = false
  applyFilters()
}

function refreshMarket() {
  if (isRefreshing.value) {
    return
  }

  isRefreshing.value = true

  router.get(route('npc-flips'), { ...filterPayload(), refresh: 1 }, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      isRefreshing.value = false
      autoRefreshRemainingSeconds.value = AUTO_REFRESH_INTERVAL_SECONDS
    },
  })
}

function formatCountdown(totalSeconds) {
  const sec = Math.max(0, Math.floor(totalSeconds))
  const m = Math.floor(sec / 60)
  const s = sec % 60
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
}

function startAutoRefreshTimer() {
  if (autoRefreshTickTimer) {
    clearInterval(autoRefreshTickTimer)
  }

  autoRefreshTickTimer = setInterval(() => {
    if (isRefreshing.value) {
      return
    }

    if (autoRefreshRemainingSeconds.value <= 1) {
      autoRefreshRemainingSeconds.value = AUTO_REFRESH_INTERVAL_SECONDS
      refreshMarket()
      return
    }

    autoRefreshRemainingSeconds.value -= 1
  }, 1000)
}

function buildPaginationUrl(page) {
  return route('npc-flips', { ...filterPayload(), page })
}

function getFlipTextureUrl(flip) {
  const productId = String(flip.product_id || '').toUpperCase()

  // Primary source: Coflnet icon renderer has best coverage for SkyBlock items.
  return COFLNET_ICON_BASE + encodeURIComponent(productId)
}

function getFlipTextureFallbackUrl(flip) {
  const productId = String(flip.product_id || '').toUpperCase()
  const vanillaPath = resolveVanillaTexturePath(productId, flip.category)

  const texture = getItemTextureUrl({
    skyblock_id: productId,
    texture_path: vanillaPath,
  })

  return texture || DEFAULT_ITEM_TEXTURE
}

function resolveVanillaTexturePath(productId, category) {
  if (PRODUCT_TEXTURE_OVERRIDES[productId]) {
    return PRODUCT_TEXTURE_OVERRIDES[productId]
  }

  const baseId = productId.split(':')[0]
  const normalized = normalizeTextureName(baseId)

  if (normalized.startsWith('rough_') && normalized.endsWith('_gem')) {
    return '/item/diamond'
  }
  if (normalized.endsWith('_gem')) {
    return '/item/diamond'
  }
  if (normalized.startsWith('enchanted_')) {
    const base = normalized.replace(/^enchanted_/, '')
    if (base) {
      return '/item/' + base
    }
  }
  if (normalized.endsWith('_ingot')) {
    return '/item/' + normalized
  }
  if (normalized.endsWith('_shard')) {
    return '/item/prismarine_shard'
  }
  if (normalized.endsWith('_bait')) {
    return '/item/fishing_rod'
  }
  if (normalized.includes('mushroom')) {
    return '/item/mushroom_stew'
  }
  if (normalized.includes('melon')) {
    return '/item/melon'
  }
  if (normalized.includes('seed')) {
    return '/item/wheat_seeds'
  }
  if (normalized.includes('cobblestone')) {
    return '/item/cobblestone'
  }
  if (normalized.includes('bone')) {
    return '/item/bone'
  }
  if (normalized.includes('ice')) {
    return '/item/ice'
  }
  if (normalized.includes('sugar_cane')) {
    return '/item/sugar_cane'
  }
  if (normalized.includes('chum') || normalized.includes('fish')) {
    return '/item/fish_cod_raw'
  }
  if (normalized.includes('log')) {
    return '/item/log'
  }

  const categoryFallback = {
    farming: '/item/wheat',
    mining: '/item/diamond_pickaxe',
    combat: '/item/iron_sword',
    fishing: '/item/fishing_rod',
    foraging: '/item/log',
    oddities: '/item/nether_star',
  }

  const fallbackByCategory = categoryFallback[String(category || '').toLowerCase()] || '/item/chest'

  if (normalized) {
    return '/item/' + normalized
  }

  return fallbackByCategory
}

function normalizeTextureName(productId) {
  return String(productId || '').toLowerCase().replace(/:/g, '_')
}

function handleTextureError(event) {
  const image = event?.target
  if (!image) return
  const fallbackUrl = image.dataset?.fallback

  if (fallbackUrl && image.dataset.fallbackApplied !== '1') {
    image.dataset.fallbackApplied = '1'
    image.src = fallbackUrl
    return
  }

  if (image.src.endsWith(DEFAULT_ITEM_TEXTURE)) return
  image.src = DEFAULT_ITEM_TEXTURE
}

function formatCoins(coins) {
  if (!Number.isFinite(coins)) {
    return '0'
  }

  if (coins <= -1_000_000) {
    return '-' + (Math.abs(coins) / 1_000_000).toFixed(2) + 'M'
  }
  if (coins <= -1_000) {
    return '-' + (Math.abs(coins) / 1_000).toFixed(1) + 'K'
  }
  if (coins >= 1_000_000) {
    return (coins / 1_000_000).toFixed(2) + 'M'
  }
  if (coins >= 1_000) {
    return (coins / 1_000).toFixed(1) + 'K'
  }

  const absCoins = Math.abs(coins)
  if (absCoins < 1) {
    return coins.toFixed(2)
  }
  if (absCoins < 10) {
    return coins.toFixed(2)
  }
  if (absCoins < 100) {
    return coins.toFixed(1)
  }

  return coins.toFixed(0)
}

function formatCompact(num) {
  if (num >= 1_000_000) {
    return (num / 1_000_000).toFixed(2) + 'M'
  }
  if (num >= 1_000) {
    return (num / 1_000).toFixed(1) + 'K'
  }
  return num.toFixed(0)
}

function formatMinutes(totalMinutes) {
  if (totalMinutes >= 120) {
    return (totalMinutes / 60).toFixed(1) + 'h'
  }
  return totalMinutes.toFixed(0) + 'm'
}

function rowClass(flip) {
  if (flip.is_ultimate_flip) {
    return 'bg-yellow-500/10 border-l-2 border-yellow-300 shadow-[inset_0_0_0_1px_rgba(251,191,36,0.4)]'
  }
  if (flip.product_id === props.best_picks?.overall?.[0]?.product_id) {
    return 'bg-yellow-500/10 border-l-2 border-yellow-400'
  }
  return ''
}

function profitClass(percent) {
  if (percent > 10) return 'text-positive'
  if (percent <= 0) return 'text-red-400'
  return 'text-text-secondary'
}

onMounted(async () => {
  await preloadAllTextures()
  startAutoRefreshTimer()
})

onBeforeUnmount(() => {
  if (autoRefreshTickTimer) {
    clearInterval(autoRefreshTickTimer)
    autoRefreshTickTimer = null
  }
})
</script>

<style scoped>
tr.flip-row-restricted {
  position: relative;
}

tr.flip-row-restricted:hover {
  background-color: rgba(15, 23, 42, 0.75);
}

tr.flip-row-restricted a,
tr.flip-row-restricted button {
  pointer-events: none;
  position: relative;
  z-index: 10;
}
</style>

