<template>
  <AuthenticatedLayout>
    <Head title="NPC Flips" />

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 class="text-3xl font-bold text-white">NPC Flipping</h1>
          <p class="text-sm text-text-secondary">Best Pick engine: tax-aware profit + volume + stackability.</p>
          <p class="text-xs text-text-tertiary mt-1">
            Active tax: {{ (((tax_meta?.rate ?? 0.01) * 100).toFixed(2)) }}% ({{ tax_meta?.source || 'default' }})
          </p>
        </div>

        <div class="flex flex-col items-end gap-1">
          <button
            @click="refreshMarket"
            class="rounded border border-border bg-surface-700 px-4 py-2 text-sm font-medium text-text-primary hover:bg-surface-600"
            :disabled="isRefreshing"
          >
            {{ isRefreshing ? 'Refreshing...' : 'Refresh Bazaar' }}
          </button>
          <span class="text-[11px] text-text-tertiary">
            Auto refresh in {{ formatCountdown(autoRefreshRemainingSeconds) }}
          </span>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
        <div>
          <label class="block text-sm font-medium text-text-primary">Search</label>
          <input
            v-model="search"
            type="text"
            placeholder="Item name..."
            class="mt-1 w-full rounded border border-border bg-surface-700 px-3 py-2 text-text-primary placeholder-text-tertiary focus:border-primary focus:outline-none"
            @input="debouncedApplyFilters"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-text-primary">Sort By</label>
          <select
            v-model="sortBy"
            class="mt-1 w-full rounded border border-border bg-surface-700 px-3 py-2 text-text-primary focus:border-primary focus:outline-none"
            @change="applyFilters"
          >
            <option value="best_pick_score">Best Pick Score</option>
            <option value="profit_per_item">Profit/Item</option>
            <option value="profit_per_inventory">Profit/Inventory</option>
            <option value="coins_per_hour">Coins/Hour</option>
            <option value="time_to_fill_minutes">Time To Fill</option>
            <option value="max_profit">Max Profit</option>
            <option value="hours_before_limited">Duration</option>
            <option value="name">Name</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-text-primary">Direction</label>
          <select
            v-model="sortDir"
            class="mt-1 w-full rounded border border-border bg-surface-700 px-3 py-2 text-text-primary focus:border-primary focus:outline-none"
            @change="applyFilters"
          >
            <option value="desc">Descending</option>
            <option value="asc">Ascending</option>
          </select>
        </div>

        <div class="flex items-end">
          <button
            @click="resetFilters"
            class="w-full rounded border border-border bg-surface-700 px-3 py-2 text-text-primary hover:bg-surface-600"
          >
            Reset
          </button>
        </div>
      </div>

      <div class="flex items-center gap-3 rounded border border-border bg-surface-800 px-4 py-3">
        <button
          @click="toggleCompactor"
          class="inline-flex items-center rounded border px-3 py-1.5 text-xs font-semibold"
          :class="hasCompactor
            ? 'border-info bg-info/20 text-info'
            : 'border-border bg-surface-700 text-text-secondary'"
        >
          I have Personal Compactor: {{ hasCompactor ? 'ON' : 'OFF' }}
        </button>
        <span class="text-xs text-text-secondary">When ON, compactable items are evaluated in compressed NPC form.</span>
      </div>

      <div v-if="best_picks?.overall?.length > 0" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-yellow-400/70 bg-[radial-gradient(circle_at_top,rgba(250,204,21,0.14),rgba(15,23,42,0.8))] p-4 text-center shadow-[0_0_0_1px_rgba(251,191,36,0.35)]">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-yellow-200">Best Overall</h3>
          <div v-if="best_picks.overall[0]" class="mt-3 flex flex-col items-center">
            <img
              :src="getFlipTextureUrl(best_picks.overall[0])"
              :data-fallback="getFlipTextureFallbackUrl(best_picks.overall[0])"
              :alt="best_picks.overall[0].name"
              class="h-12 w-12 object-contain"
              loading="lazy"
              @error="handleTextureError"
            />
            <div class="mt-2 font-semibold text-white">{{ best_picks.overall[0].name }}</div>
            <div class="text-sm text-positive">{{ formatCoins(best_picks.overall[0].profit_per_item) }}/item</div>
            <div class="text-xs text-text-secondary">Score {{ best_picks.overall[0].best_pick_score.toFixed(1) }}</div>
          </div>
        </div>

        <div class="rounded-xl border border-border bg-surface-800 p-4 text-center">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-cyan-200">Best Stackable</h3>
          <div v-if="best_picks.stackable?.[0]" class="mt-3 flex flex-col items-center">
            <img
              :src="getFlipTextureUrl(best_picks.stackable[0])"
              :data-fallback="getFlipTextureFallbackUrl(best_picks.stackable[0])"
              :alt="best_picks.stackable[0].name"
              class="h-12 w-12 object-contain"
              loading="lazy"
              @error="handleTextureError"
            />
            <div class="mt-2 font-semibold text-white">{{ best_picks.stackable[0].name }}</div>
            <div class="text-sm text-text-secondary">{{ formatCoins(best_picks.stackable[0].coins_per_hour) }}/hour</div>
          </div>
        </div>

        <div class="rounded-xl border border-border bg-surface-800 p-4 text-center">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-rose-200">Highest Throughput</h3>
          <div v-if="best_picks.hourly_profit[0]" class="mt-3 flex flex-col items-center">
            <img
              :src="getFlipTextureUrl(best_picks.hourly_profit[0])"
              :data-fallback="getFlipTextureFallbackUrl(best_picks.hourly_profit[0])"
              :alt="best_picks.hourly_profit[0].name"
              class="h-12 w-12 object-contain"
              loading="lazy"
              @error="handleTextureError"
            />
            <div class="mt-2 font-semibold text-white">{{ best_picks.hourly_profit[0].name }}</div>
            <div class="text-sm text-text-secondary">{{ formatCoins(best_picks.hourly_profit[0].coins_per_hour) }}/hour</div>
          </div>
        </div>
      </div>

      <div class="rounded border border-border bg-surface-800">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="border-b border-border bg-surface-700">
              <tr>
                <th class="px-4 py-3 text-left font-semibold text-text-primary">
                  <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="toggleSort('name')">
                    Item <span class="text-xs text-text-tertiary">{{ sortIndicator('name') }}</span>
                  </button>
                </th>
                <th class="px-4 py-3 text-center font-semibold text-text-primary">
                  <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="toggleSort('is_stackable')">
                    Stack <span class="text-xs text-text-tertiary">{{ sortIndicator('is_stackable') }}</span>
                  </button>
                </th>
                <th class="px-4 py-3 text-right font-semibold text-text-primary">
                  <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="toggleSort('best_pick_score')">
                    Best Score <span class="text-xs text-text-tertiary">{{ sortIndicator('best_pick_score') }}</span>
                  </button>
                </th>
                <th class="px-4 py-3 text-right font-semibold text-text-primary">
                  <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="toggleSort('buy_price')">
                    BZ Buy <span class="text-xs text-text-tertiary">{{ sortIndicator('buy_price') }}</span>
                  </button>
                </th>
                <th class="px-4 py-3 text-right font-semibold text-text-primary">
                  <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="toggleSort('npc_sell_price')">
                    NPC Sell <span class="text-xs text-text-tertiary">{{ sortIndicator('npc_sell_price') }}</span>
                  </button>
                </th>
                <th class="px-4 py-3 text-right font-semibold text-text-primary">
                  <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="toggleSort('profit_per_item')">
                    Profit/Item <span class="text-xs text-text-tertiary">{{ sortIndicator('profit_per_item') }}</span>
                  </button>
                </th>
                <th class="px-4 py-3 text-right font-semibold text-text-primary">
                  <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="toggleSort('one_hour_instasells')">
                    1h Volume <span class="text-xs text-text-tertiary">{{ sortIndicator('one_hour_instasells') }}</span>
                  </button>
                </th>
                <th class="px-4 py-3 text-right font-semibold text-text-primary">
                  <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="toggleSort('coins_per_hour')">
                    Coins/Hour <span class="text-xs text-text-tertiary">{{ sortIndicator('coins_per_hour') }}</span>
                  </button>
                </th>
                <th class="px-4 py-3 text-right font-semibold text-text-primary">
                  <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="toggleSort('profit_per_inventory')">
                    Profit/Inventory <span class="text-xs text-text-tertiary">{{ sortIndicator('profit_per_inventory') }}</span>
                  </button>
                </th>
                <th class="px-4 py-3 text-right font-semibold text-text-primary">
                  <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="toggleSort('time_to_fill_minutes')">
                    Time To Fill <span class="text-xs text-text-tertiary">{{ sortIndicator('time_to_fill_minutes') }}</span>
                  </button>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              <tr
                v-for="flip in flips"
                :key="flip.product_id"
                class="hover:bg-surface-700/50"
                :class="rowClass(flip)"
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
                    <span v-if="flip.can_compact" class="rounded bg-info/20 px-1.5 py-0.5 text-[10px] font-semibold text-info">PC</span>
                    <span v-if="flip.is_ultimate_flip" class="rounded bg-warning/20 px-1.5 py-0.5 text-[10px] font-semibold text-warning">ULTIMATE FLIP</span>
                  </div>
                  <div v-if="flip.can_compact && flip.compactor_target_id" class="text-[11px] text-text-secondary">
                    Compacts to {{ flip.compactor_target_id }} (x{{ flip.compactor_ratio.toFixed(0) }})
                  </div>
                </td>

                <td class="px-4 py-3 text-center">
                  <span
                    class="inline-block rounded px-2 py-1 text-xs font-semibold"
                    :class="flip.is_stackable ? 'bg-info/20 text-info' : 'bg-surface-600 text-text-secondary'"
                  >
                    {{ flip.is_stackable ? 'Yes' : 'No' }}
                  </span>
                </td>

                <td class="px-4 py-3 text-right font-semibold text-white">{{ flip.best_pick_score.toFixed(1) }}</td>
                <td class="px-4 py-3 text-right text-text-secondary">{{ formatCoins(flip.buy_price) }}</td>
                <td class="px-4 py-3 text-right font-semibold text-white">{{ formatCoins(flip.npc_sell_price) }}</td>

                <td class="px-4 py-3 text-right">
                  <div class="font-semibold" :class="profitClass(flip.profit_percent)">
                    {{ formatCoins(flip.profit_per_item) }}
                  </div>
                  <div class="text-xs" :class="profitClass(flip.profit_percent)">
                    {{ flip.profit_percent.toFixed(1) }}%
                  </div>
                  <div v-if="flip.profit_percent < 2" class="text-xs text-red-400">Too close to NPC</div>
                </td>

                <td class="px-4 py-3 text-right text-text-secondary">{{ formatCompact(flip.one_hour_instasells) }}</td>
                <td class="px-4 py-3 text-right font-semibold text-white">{{ formatCoins(flip.coins_per_hour) }}</td>
                <td class="px-4 py-3 text-right text-white">{{ formatCoins(flip.profit_per_inventory) }}</td>
                <td class="px-4 py-3 text-right text-text-secondary">{{ formatMinutes(flip.time_to_fill_minutes) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="flips.length === 0" class="px-4 py-8 text-center text-text-secondary">
          No NPC flips found. Try adjusting filters.
        </div>
      </div>

      <div v-if="pagination.last_page > 1" class="flex items-center justify-between">
        <div class="text-sm text-text-secondary">
          Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} total)
        </div>
        <div class="flex gap-2">
          <Link
            v-if="pagination.current_page > 1"
            :href="buildPaginationUrl(1)"
            class="rounded border border-border bg-surface-700 px-3 py-2 text-sm text-text-primary hover:bg-surface-600"
          >
            First
          </Link>
          <Link
            v-if="pagination.current_page > 1"
            :href="buildPaginationUrl(pagination.current_page - 1)"
            class="rounded border border-border bg-surface-700 px-3 py-2 text-sm text-text-primary hover:bg-surface-600"
          >
            Previous
          </Link>
          <Link
            v-if="pagination.current_page < pagination.last_page"
            :href="buildPaginationUrl(pagination.current_page + 1)"
            class="rounded border border-border bg-surface-700 px-3 py-2 text-sm text-text-primary hover:bg-surface-600"
          >
            Next
          </Link>
          <Link
            v-if="pagination.current_page < pagination.last_page"
            :href="buildPaginationUrl(pagination.last_page)"
            class="rounded border border-border bg-surface-700 px-3 py-2 text-sm text-text-primary hover:bg-surface-600"
          >
            Last
          </Link>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { getItemTextureUrl, preloadAllTextures } from '@/utils/textures'

const props = defineProps({
  flips: Array,
  pagination: Object,
  filters: Object,
  best_picks: Object,
  tax_meta: Object,
  has_compactor: Boolean,
})

const search = ref(props.filters.search || '')
const sortBy = ref(props.filters.sort || 'best_pick_score')
const sortDir = ref(props.filters.dir || 'desc')
const isRefreshing = ref(false)

const AUTO_REFRESH_INTERVAL_SECONDS = 180
const autoRefreshRemainingSeconds = ref(AUTO_REFRESH_INTERVAL_SECONDS)

let debounceTimer = null
let autoRefreshTickTimer = null
const DEFAULT_ITEM_TEXTURE = '/img/textures/chest.png'
const COFLNET_ICON_BASE = 'https://sky.coflnet.com/static/icon/'
const SKYCRYPT_ICON_BASE = 'https://sky.shiiyu.moe/api/item/'

const PRODUCT_TEXTURE_OVERRIDES = {
  ICE_BAIT: '/item/fish_cod_raw',
  LUSHILAC: '/item/double_plant',
  SNOW_BALL: '/item/snowball',
  SUGAR_CANE: '/item/sugar_cane',
  WILD_ROSE: '/item/red_flower',
  SEEDS: '/item/wheat_seeds',
  PRISMARINE_SHARD: '/item/prismarine_shard',
}

function debouncedApplyFilters() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 300)
}

function applyFilters() {
  router.get(route('npc-flips'), {
    search: search.value || undefined,
    sort: sortBy.value,
    dir: sortDir.value,
    has_compactor: hasCompactor.value ? 1 : 0,
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}

function toggleSort(column) {
  if (sortBy.value === column) {
    sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc'
  } else {
    sortBy.value = column
    sortDir.value = 'desc'
  }

  applyFilters()
}

function sortIndicator(column) {
  if (sortBy.value !== column) {
    return '↕'
  }

  return sortDir.value === 'desc' ? '↓' : '↑'
}

function resetFilters() {
  search.value = ''
  sortBy.value = 'best_pick_score'
  sortDir.value = 'desc'
  applyFilters()
}

function refreshMarket() {
  if (isRefreshing.value) {
    return
  }

  isRefreshing.value = true

  router.get(route('npc-flips'), {
    search: search.value || undefined,
    sort: sortBy.value,
    dir: sortDir.value,
    has_compactor: hasCompactor.value ? 1 : 0,
    refresh: 1,
  }, {
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
  return route('npc-flips', {
    search: search.value || undefined,
    sort: sortBy.value,
    dir: sortDir.value,
    has_compactor: hasCompactor.value ? 1 : 0,
    page,
  })
}

const hasCompactor = ref(Boolean(props.has_compactor))

function toggleCompactor() {
  hasCompactor.value = !hasCompactor.value
  applyFilters()
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

