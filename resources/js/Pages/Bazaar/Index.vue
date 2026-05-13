<template>
  <AuthenticatedLayout>
    <Head :title="t('bazaar.title')" />

    <div class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
      <div class="flex flex-col gap-4 border-b border-white/[0.06] pb-5 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0 space-y-1.5">
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-text-tertiary">{{ t('nav.bazaar') }}</p>
          <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ t('bazaar.title') }}</h1>
          <p v-if="t('bazaar.subtitle')" class="text-sm leading-relaxed text-text-secondary">{{ t('bazaar.subtitle') }}</p>
          <p class="text-xs leading-relaxed text-text-tertiary">
            {{ t('bazaar.taxSummary', {
              buy: (Number(flipTax.instant_buy_tax_rate) * 100).toFixed(2),
              sell: (Number(flipTax.instant_sell_tax_rate) * 100).toFixed(2),
              source: buy_tax_meta?.source || 'default',
            }) }}
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

      <div :class="bazaarSurface" class="p-4 sm:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:gap-4">
          <div class="min-w-0 flex-1">
            <label class="block text-xs font-medium text-text-secondary">{{ t('common.search') }}</label>
            <input
              v-model="search"
              type="text"
              :placeholder="t('bazaar.itemPlaceholder')"
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
                  <option value="coins_per_hour">{{ t('common.coinsPerHour') }}</option>
                  <option value="margin">{{ t('common.margin') }}</option>
                  <option value="margin_percent">{{ t('bazaar.marginPercent') }}</option>
                  <option value="buy_price">{{ t('bazaar.instaSellRevenue') }}</option>
                  <option value="sell_price">{{ t('bazaar.instaBuyCost') }}</option>
                  <option value="hourly_instabuys">{{ t('common.volume1h') }}</option>
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
                <label class="block text-xs font-medium text-text-secondary">{{ t('bazaar.minDailyVolume') }}</label>
                <input
                  v-model.number="minDailyVolume"
                  type="number"
                  min="0"
                  step="10"
                  :class="fieldInputClass"
                  @change="applyFilters"
                />
                <p class="mt-1 text-[10px] text-text-tertiary">{{ t('bazaar.minDailyVolumeHint') }}</p>
              </div>

              <div>
                <label class="block text-xs font-medium text-text-secondary">{{ t('bazaar.maxEntryCost') }}</label>
                <input
                  v-model.number="maxEntryCost"
                  type="number"
                  min="0"
                  step="1"
                  :class="fieldInputClass"
                  :placeholder="t('bazaar.optional')"
                  @change="applyFilters"
                />
                <p class="mt-1 text-[10px] text-text-tertiary">{{ t('bazaar.maxEntryCostHint') }}</p>
              </div>

              <div>
                <label class="block text-xs font-medium text-text-secondary">{{ t('bazaar.minMarginCoins') }}</label>
                <input
                  v-model.number="minMargin"
                  type="number"
                  min="0"
                  step="1"
                  :class="fieldInputClass"
                  :placeholder="t('bazaar.optional')"
                  @change="applyFilters"
                />
              </div>

              <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-text-secondary">{{ t('bazaar.minMarginPercent') }}</label>
                <input
                  v-model.number="minMarginPercent"
                  type="number"
                  min="0"
                  step="0.1"
                  :class="fieldInputClass"
                  :placeholder="t('bazaar.optional')"
                  @change="applyFilters"
                />
                <p class="mt-1 text-[10px] text-text-tertiary">{{ t('bazaar.minMarginPercentHint') }}</p>
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

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div
          v-for="index in 3"
          :key="`top-flip-${index}`"
          :class="[bazaarSurface, 'p-4 text-center']"
        >
          <h3 class="text-[11px] font-semibold uppercase tracking-[0.2em] text-text-tertiary">{{ t('bazaar.topFlip', { n: index }) }}</h3>
          <template v-if="top_flips?.[index - 1]">
            <div class="top-flip-content mt-3 flex flex-col items-center">
              <img
                :src="getTextureUrl(top_flips[index - 1].product_id)"
                :data-fallback="getTextureFallbackUrl(top_flips[index - 1].product_id)"
                :alt="top_flips[index - 1].name"
                class="h-12 w-12 object-contain"
                loading="lazy"
                @error="handleTextureError"
              />
              <div class="mt-2 line-clamp-2 font-semibold text-white">{{ top_flips[index - 1].name }}</div>
              <div class="text-sm text-positive">{{ formatCompact(top_flips[index - 1].coins_per_hour) }}{{ t('bazaar.perHour') }}</div>
              <div class="text-[11px] text-text-tertiary">{{ t('bazaar.topFlipMargin', { m: formatCoins(top_flips[index - 1].margin) }) }}</div>
            </div>
          </template>
          <template v-else>
            <div class="mt-3 text-sm text-text-secondary">{{ t('common.noData') }}</div>
          </template>
        </div>
      </div>

      <div :class="[bazaarSurface, 'overflow-hidden']">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="sr-only">
              <tr>
                <th scope="col">{{ t('common.item') }}</th>
                <th scope="col">{{ t('bazaar.instaSellRevenue') }}</th>
                <th scope="col">{{ t('bazaar.instaBuyCost') }}</th>
                <th scope="col">{{ t('common.margin') }}</th>
                <th scope="col">{{ t('common.volume1h') }}</th>
                <th scope="col">{{ t('common.coinsPerHour') }}</th>
                <th scope="col">{{ t('common.action') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border/50">
              <tr
                v-for="(item, index) in liveItems"
                :key="item.product_id"
                class="transition hover:bg-white/[0.03]"
                :class="[rowClass(item), isRestrictedFlip(index) ? 'flip-row-restricted' : '']"
              >
                <td class="px-4 py-3 font-semibold text-white">
                  <div class="flex items-center gap-2">
                    <img
                      :src="getTextureUrl(item.product_id)"
                      :data-fallback="getTextureFallbackUrl(item.product_id)"
                      :alt="item.name"
                      class="h-10 w-10 shrink-0 object-contain"
                      loading="lazy"
                      @error="handleTextureError"
                    />
                    <div>
                      <span>{{ item.name }}</span>
                      <div class="text-[11px] text-text-secondary">{{ item.product_id }}</div>
                    </div>
                  </div>
                </td>

                <td class="px-4 py-3 text-right text-text-secondary" :aria-label="t('bazaar.instaSellRevenue')">
                  {{ formatCoins(item.buy_price) }}
                  <span class="ml-1 text-xs" :class="trendClass(buyTrend[item.product_id] || 'flat')">{{ trendIcon(buyTrend[item.product_id] || 'flat') }}</span>
                </td>

                <td class="px-4 py-3 text-right text-text-secondary" :aria-label="t('bazaar.instaBuyCost')">
                  {{ formatCoins(item.sell_price) }}
                  <span class="ml-1 text-xs" :class="trendClass(sellTrend[item.product_id] || 'flat')">{{ trendIcon(sellTrend[item.product_id] || 'flat') }}</span>
                </td>

                <td class="px-4 py-3 text-right">
                  <div class="font-semibold" :class="profitClass(marginPercent(item))">
                    {{ formatCoins(margin(item)) }}
                  </div>
                  <div class="text-xs" :class="profitClass(marginPercent(item))">
                    {{ marginPercent(item).toFixed(1) }}%
                  </div>
                </td>

                <td class="px-4 py-3 text-right text-text-secondary">
                  <div>{{ formatCompact(hourlyInstabuys(item)) }} / {{ formatCompact(hourlyInstasells(item)) }}</div>
                  <div class="text-[10px] text-text-tertiary">{{ t('bazaar.buysSells') }}</div>
                </td>

                <td class="px-4 py-3 text-right font-semibold text-white">{{ formatCompact(coinsPerHour(item)) }}</td>

                <td class="px-4 py-3 text-center">
                  <button
                    type="button"
                    class="rounded-lg border border-border/80 bg-surface-800/80 px-2.5 py-1.5 text-xs font-semibold text-text-primary transition hover:border-profit/30 hover:bg-profit/10 hover:text-white"
                    @click="copyItemCommand(item.product_id)"
                  >
                    {{ t('bazaar.copyBz') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="liveItems.length === 0" class="px-4 py-10 text-center text-sm text-text-secondary">
          {{ t('bazaar.noFlips') }}
        </div>

        <div v-else class="border-t border-border/60 px-4 py-2.5 text-[11px] leading-relaxed text-text-tertiary">
          {{ t('bazaar.tableFootnote') }}
        </div>
      </div>

      <div v-if="pagination.last_page > 1" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="text-sm text-text-secondary">
          {{ t('common.page') }} {{ pagination.current_page }} {{ t('common.of') }} {{ pagination.last_page }} ({{ pagination.total }} {{ t('common.total') }})
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            v-if="pagination.current_page > 1"
            type="button"
            class="rounded-xl border border-border/80 bg-surface-900/75 px-3 py-2 text-sm text-text-primary shadow-[0_8px_24px_rgba(0,0,0,0.2)] backdrop-blur-sm transition hover:border-profit/30 hover:bg-profit/10 hover:text-white"
            @click="goToPage(1)"
          >
            {{ t('common.first') }}
          </button>
          <button
            v-if="pagination.current_page > 1"
            type="button"
            class="rounded-xl border border-border/80 bg-surface-900/75 px-3 py-2 text-sm text-text-primary shadow-[0_8px_24px_rgba(0,0,0,0.2)] backdrop-blur-sm transition hover:border-profit/30 hover:bg-profit/10 hover:text-white"
            @click="goToPage(pagination.current_page - 1)"
          >
            {{ t('common.previous') }}
          </button>
          <button
            v-if="pagination.current_page < pagination.last_page"
            type="button"
            class="rounded-xl border border-border/80 bg-surface-900/75 px-3 py-2 text-sm text-text-primary shadow-[0_8px_24px_rgba(0,0,0,0.2)] backdrop-blur-sm transition hover:border-profit/30 hover:bg-profit/10 hover:text-white"
            @click="goToPage(pagination.current_page + 1)"
          >
            {{ t('common.next') }}
          </button>
          <button
            v-if="pagination.current_page < pagination.last_page"
            type="button"
            class="rounded-xl border border-border/80 bg-surface-900/75 px-3 py-2 text-sm text-text-primary shadow-[0_8px_24px_rgba(0,0,0,0.2)] backdrop-blur-sm transition hover:border-profit/30 hover:bg-profit/10 hover:text-white"
            @click="goToPage(pagination.last_page)"
          >
            {{ t('common.last') }}
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from '@/strings/useI18n'

const { t } = useI18n()

/** Same glass panel as landing search bar */
const bazaarSurface =
  'rounded-2xl border border-border/80 bg-surface-900/75 shadow-[0_16px_40px_rgba(0,0,0,0.35)] backdrop-blur-sm'

const fieldInputClass =
  'mt-1 w-full rounded-xl border border-border/80 bg-surface-800/80 px-3 py-2 text-sm text-text-primary placeholder:text-text-tertiary transition focus:border-profit/55 focus:outline-none focus:ring-2 focus:ring-profit/15'

const props = defineProps({
  items: Object,
  best_picks: Object,
  top_flips: Array,
  flip_tax: {
    type: Object,
    default: () => ({
      instant_buy_tax_rate: 0.01,
      instant_sell_tax_rate: 0.0125,
      sell_keep_multiplier: 0.9875,
      buy_cost_multiplier: 1.01,
    }),
  },
  buy_tax_meta: Object,
  categories: {
    type: Array,
    default: () => [],
  },
  filters: Object,
})

const isRestrictedFlip = () => false

const pagination = ref({
  current_page: props.items.current_page,
  last_page: props.items.last_page,
  total: props.items.total,
})

const liveItems = ref([...props.items.data])
const buyTrend = ref({})
const sellTrend = ref({})

const search = ref(props.filters.search || '')
const category = ref(props.filters.category || '')
const sortBy = ref(props.filters.sort || 'coins_per_hour')
const sortDir = ref(props.filters.dir || 'desc')
const minDailyVolume = ref(props.filters.min_daily_volume ?? 100)
const maxEntryCost = ref(props.filters.max_entry_cost ?? '')
const minMargin = ref(props.filters.min_margin ?? '')
const minMarginPercent = ref(props.filters.min_margin_percent ?? '')
const isRefreshing = ref(false)

const flipTax = computed(() => props.flip_tax || {})
const AUTO_REFRESH_INTERVAL_SECONDS = 180
const autoRefreshRemainingSeconds = ref(AUTO_REFRESH_INTERVAL_SECONDS)

let debounceTimer = null
let echoChannel = null
let autoRefreshTickTimer = null

const COFLNET_ICON_BASE = 'https://sky.coflnet.com/static/icon/'
const ITEM_TEXTURE_ICON_BASE = 'https://sky.shiiyu.moe/api/item/'
const DEFAULT_ITEM_TEXTURE = '/img/textures/chest.png'

watch(() => props.items, (newItems) => {
  liveItems.value = [...newItems.data]
  pagination.value = {
    current_page: newItems.current_page,
    last_page: newItems.last_page,
    total: newItems.total,
  }
})

watch(() => props.filters, (f) => {
  if (!f) return
  search.value = f.search || ''
  category.value = f.category || ''
  sortBy.value = f.sort || 'coins_per_hour'
  sortDir.value = f.dir || 'desc'
  minDailyVolume.value = f.min_daily_volume ?? 100
  maxEntryCost.value = f.max_entry_cost ?? ''
  minMargin.value = f.min_margin ?? ''
  minMarginPercent.value = f.min_margin_percent ?? ''
}, { deep: true })

function filterPayload(extra = {}) {
  const payload = {
    search: search.value || undefined,
    category: category.value || undefined,
    sort: sortBy.value,
    dir: sortDir.value,
    min_daily_volume: minDailyVolume.value === '' || minDailyVolume.value === null ? undefined : minDailyVolume.value,
    max_entry_cost: maxEntryCost.value === '' || maxEntryCost.value === null ? undefined : maxEntryCost.value,
    min_margin: minMargin.value === '' || minMargin.value === null ? undefined : minMargin.value,
    min_margin_percent: minMarginPercent.value === '' || minMarginPercent.value === null ? undefined : minMarginPercent.value,
    ...extra,
  }
  return Object.fromEntries(Object.entries(payload).filter(([, v]) => v !== undefined && v !== ''))
}

function debouncedApplyFilters() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 300)
}

function applyFilters() {
  router.get(route('bazaar'), filterPayload(), {
    preserveState: true,
    preserveScroll: true,
  })
}

function resetFilters() {
  search.value = ''
  category.value = ''
  sortBy.value = 'coins_per_hour'
  sortDir.value = 'desc'
  minDailyVolume.value = 100
  maxEntryCost.value = ''
  minMargin.value = ''
  minMarginPercent.value = ''
  applyFilters()
}

function refreshMarket() {
  if (isRefreshing.value) return

  isRefreshing.value = true
  router.get(route('bazaar'), { ...filterPayload(), refresh: 1 }, {
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
  if (autoRefreshTickTimer) clearInterval(autoRefreshTickTimer)

  autoRefreshTickTimer = setInterval(() => {
    if (isRefreshing.value) return
    if (autoRefreshRemainingSeconds.value <= 1) {
      autoRefreshRemainingSeconds.value = AUTO_REFRESH_INTERVAL_SECONDS
      refreshMarket()
      return
    }
    autoRefreshRemainingSeconds.value -= 1
  }, 1000)
}

function goToPage(page) {
  router.get(route('bazaar'), { ...filterPayload(), page }, {
    preserveState: true,
    preserveScroll: true,
  })
}

function getTextureUrl(productId) {
  return COFLNET_ICON_BASE + encodeURIComponent(String(productId || '').toUpperCase())
}

function getTextureFallbackUrl(productId) {
  const pid = String(productId || '').toUpperCase()
  return ITEM_TEXTURE_ICON_BASE + encodeURIComponent(pid)
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

function sellKeepMult() {
  const m = Number(flipTax.value.sell_keep_multiplier)
  return Number.isFinite(m) && m > 0 ? m : 0.9875
}

function buyCostMult() {
  const m = Number(flipTax.value.buy_cost_multiplier)
  return Number.isFinite(m) && m >= 1 ? m : 1.01
}

function margin(item) {
  const buy = Number(item.buy_price || 0)
  const sell = Number(item.sell_price || 0)
  return buy * sellKeepMult() - sell * buyCostMult()
}

function marginPercent(item) {
  const sell = Number(item.sell_price || 0)
  const entry = sell * buyCostMult()
  if (entry <= 0) return 0
  return (margin(item) / entry) * 100
}

function hourlyInstabuys(item) {
  return Number(item.sell_moving_week || 0) / 168
}

function hourlyInstasells(item) {
  return Number(item.buy_moving_week || 0) / 168
}

function coinsPerHour(item) {
  const m = margin(item)
  if (m <= 0) return 0
  return m * Math.min(hourlyInstabuys(item), hourlyInstasells(item))
}

function formatCoins(coins) {
  if (!Number.isFinite(Number(coins))) return '0'
  const num = Number(coins)
  const abs = Math.abs(num)
  if (abs >= 1_000_000) return (num / 1_000_000).toFixed(2) + 'M'
  if (abs >= 1_000) return (num / 1_000).toFixed(1) + 'K'
  if (abs < 1) return num.toFixed(2)
  if (abs < 10) return num.toFixed(2)
  if (abs < 100) return num.toFixed(1)
  return num.toFixed(0)
}

function formatCompact(num) {
  const n = Number(num)
  if (!Number.isFinite(n)) return '0'
  if (Math.abs(n) >= 1_000_000) return (n / 1_000_000).toFixed(2) + 'M'
  if (Math.abs(n) >= 1_000) return (n / 1_000).toFixed(1) + 'K'
  return n.toFixed(0)
}

function trendDirection(previous, current) {
  if (current > previous) return 'up'
  if (current < previous) return 'down'
  return 'flat'
}

function trendIcon(direction) {
  if (direction === 'up') return '▲'
  if (direction === 'down') return '▼'
  return ''
}

function trendClass(direction) {
  if (direction === 'up') return 'text-positive'
  if (direction === 'down') return 'text-red-400'
  return 'text-text-tertiary'
}

function rowClass(item) {
  if (props.top_flips?.[0]?.product_id === item.product_id) {
    return 'bg-yellow-500/10 border-l-2 border-yellow-400'
  }
  return ''
}

function profitClass(percent) {
  if (percent > 10) return 'text-positive'
  if (percent <= 0) return 'text-red-400'
  return 'text-text-secondary'
}

async function copyItemCommand(productId) {
  const text = `/bz ${String(productId || '').trim()}`
  try {
    await navigator.clipboard.writeText(text)
  } catch {
    const textarea = document.createElement('textarea')
    textarea.value = text
    textarea.style.position = 'fixed'
    textarea.style.opacity = '0'
    document.body.appendChild(textarea)
    textarea.select()
    document.execCommand('copy')
    document.body.removeChild(textarea)
  }
}

onMounted(() => {
  startAutoRefreshTimer()

  if (window.Echo) {
    echoChannel = window.Echo.channel('bazaar')
    echoChannel.listen('.data.updated', (e) => {
      if (!e.items) return

      liveItems.value = liveItems.value.map((item) => {
        const update = e.items[item.product_id]
        if (!update) return item

        const prevBuy = Number(item.buy_price || 0)
        const prevSell = Number(item.sell_price || 0)
        const nextBuy = Number(update.buy_price || 0)
        const nextSell = Number(update.sell_price || 0)

        buyTrend.value[item.product_id] = trendDirection(prevBuy, nextBuy)
        sellTrend.value[item.product_id] = trendDirection(prevSell, nextSell)

        return {
          ...item,
          sell_price: nextSell,
          buy_price: nextBuy,
          sell_volume: Number(update.sell_volume || 0),
          buy_volume: Number(update.buy_volume || 0),
          sell_orders: Number(update.sell_orders || 0),
          buy_orders: Number(update.buy_orders || 0),
          sell_moving_week: Number(update.sell_moving_week || item.sell_moving_week || 0),
          buy_moving_week: Number(update.buy_moving_week || item.buy_moving_week || 0),
        }
      })
    })
  }
})

onBeforeUnmount(() => {
  if (autoRefreshTickTimer) {
    clearInterval(autoRefreshTickTimer)
    autoRefreshTickTimer = null
  }
  if (echoChannel && window.Echo) {
    window.Echo.leave('bazaar')
  }
  clearTimeout(debounceTimer)
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
