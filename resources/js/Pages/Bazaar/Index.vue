<template>
  <AuthenticatedLayout>
    <Head :title="t('bazaar.title')" />

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 class="text-3xl font-bold text-white">{{ t('bazaar.title') }}</h1>
          <p class="text-sm text-text-secondary">{{ t('bazaar.subtitle') }}</p>
        </div>

        <div class="flex flex-col items-end gap-1">
          <button
            @click="refreshMarket"
            class="rounded border border-border bg-surface-700 px-4 py-2 text-sm font-medium text-text-primary hover:bg-surface-600"
            :disabled="isRefreshing"
          >
            {{ isRefreshing ? t('common.refreshing') : t('bazaar.refreshBazaar') }}
          </button>
          <span class="text-[11px] text-text-tertiary">
            {{ t('common.autoRefreshIn') }} {{ formatCountdown(autoRefreshRemainingSeconds) }}
          </span>
        </div>
      </div>

      <!-- Filters -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
        <div>
          <label class="block text-sm font-medium text-text-primary">{{ t('common.search') }}</label>
          <input
            v-model="search"
            type="text"
            :placeholder="t('bazaar.itemPlaceholder')"
            class="mt-1 w-full rounded border border-border bg-surface-700 px-3 py-2 text-text-primary placeholder-text-tertiary focus:border-primary focus:outline-none"
            @input="debouncedApplyFilters"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-text-primary">{{ t('common.sortBy') }}</label>
          <select
            v-model="sortBy"
            class="mt-1 w-full rounded border border-border bg-surface-700 px-3 py-2 text-text-primary focus:border-primary focus:outline-none"
            @change="applyFilters"
          >
            <option value="coins_per_hour">{{ t('common.coinsPerHour') }}</option>
            <option value="margin">{{ t('common.margin') }}</option>
            <option value="margin_percent">{{ t('bazaar.marginPercent') }}</option>
            <option value="buy_price">{{ t('bazaar.buyPrice') }}</option>
            <option value="sell_price">{{ t('bazaar.sellPrice') }}</option>
            <option value="hourly_instabuys">{{ t('common.volume1h') }}</option>
            <option value="name">{{ t('common.name') }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-text-primary">{{ t('common.direction') }}</label>
          <select
            v-model="sortDir"
            class="mt-1 w-full rounded border border-border bg-surface-700 px-3 py-2 text-text-primary focus:border-primary focus:outline-none"
            @change="applyFilters"
          >
            <option value="desc">{{ t('common.descending') }}</option>
            <option value="asc">{{ t('common.ascending') }}</option>
          </select>
        </div>

        <div class="flex items-end">
          <button
            @click="resetFilters"
            class="w-full rounded border border-border bg-surface-700 px-3 py-2 text-text-primary hover:bg-surface-600"
          >
            {{ t('common.reset') }}
          </button>
        </div>
      </div>

      <!-- Top flips (tier gated) -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div
          v-for="index in 3"
          :key="`top-flip-${index}`"
          class="rounded-xl border bg-surface-800 p-4 text-center"
          :class="index === 1 ? 'border-yellow-400/70 bg-[radial-gradient(circle_at_top,rgba(250,204,21,0.14),rgba(15,23,42,0.8))]' : 'border-border'"
        >
          <h3 class="text-xs font-semibold uppercase tracking-wide" :class="index === 1 ? 'text-yellow-200' : 'text-cyan-200'">{{ t('bazaar.topFlip', { n: index }) }}</h3>
          <template v-if="top_flips?.[index - 1]">
            <div class="mt-3 flex flex-col items-center">
              <img
                :src="getTextureUrl(top_flips[index - 1].product_id)"
                :data-fallback="getTextureFallbackUrl(top_flips[index - 1].product_id)"
                :alt="top_flips[index - 1].name"
                class="h-12 w-12 object-contain"
                loading="lazy"
                @error="handleTextureError"
              />
              <div class="mt-2 font-semibold text-white">{{ top_flips[index - 1].name }}</div>
              <div class="text-sm text-positive">{{ formatCompact(top_flips[index - 1].coins_per_hour) }}{{ t('bazaar.perHour') }}</div>
            </div>
          </template>
          <template v-else>
            <div class="mt-3 text-sm text-text-secondary">
              <span v-if="index > 1">{{ t('bazaar.lockedFree') }}</span>
              <span v-else>{{ t('common.noData') }}</span>
            </div>
          </template>
        </div>
      </div>

      <div v-if="!isMvp" class="rounded-xl border border-fuchsia-300/25 bg-[radial-gradient(circle_at_top,rgba(217,70,239,0.16),rgba(15,23,42,0.88))] p-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h3 class="text-base font-semibold text-fuchsia-100">{{ isVipOrHigher ? t('bazaar.maybeAiUpgradeTitle') : t('bazaar.upgradeVipTitle') }}</h3>
            <p class="mt-1 text-sm text-fuchsia-100/80">{{ isVipOrHigher ? t('bazaar.aiMvpOnly') : t('bazaar.upgradeVipDesc') }}</p>
            <p class="mt-1 text-xs text-fuchsia-100/65">{{ t('bazaar.maybeAiUpgradeDesc') }}</p>
          </div>
          <div class="flex gap-2">
            <Link :href="route('billing')" class="rounded-lg bg-fuchsia-400 px-4 py-2 text-xs font-semibold text-black transition hover:bg-fuchsia-300">
              {{ isVipOrHigher ? t('bazaar.unlockMvpCta') : t('bazaar.upgradeVipCta') }}
            </Link>
                <Link :href="`${route('billing')}#faq`" class="rounded-lg border border-fuchsia-200/35 bg-black/20 px-4 py-2 text-xs font-semibold text-fuchsia-100 transition hover:bg-black/30">
              {{ t('bazaar.comparePlansCta') }}
            </Link>
          </div>
        </div>
      </div>

      <div v-if="subscriptionFeatures?.can_ai_flips" class="rounded-xl border border-cyan-400/30 bg-cyan-400/10 p-4">
        <h3 class="text-sm font-semibold text-cyan-100">{{ t('bazaar.aiTitle') }}</h3>
        <p class="mt-1 text-xs text-cyan-100/70">{{ t('bazaar.aiDesc') }}</p>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
          <div v-for="insight in ai_insights" :key="`ai-${insight.product_id}`" class="rounded-lg border border-cyan-300/30 bg-surface-800/70 p-3">
            <p class="text-sm font-semibold text-white">{{ insight.name }}</p>
            <p class="text-xs text-cyan-100/80">{{ t('bazaar.trustScore', { score: insight.trust_score }) }}</p>
            <p class="text-xs text-cyan-100/70">{{ insight.risk }}</p>
          </div>
        </div>
      </div>
      <div v-else class="rounded-xl border border-cyan-400/30 bg-cyan-400/10 p-4 text-sm text-cyan-100/80">
        {{ t('bazaar.aiDesc') }}
      </div>

      <!-- Table -->
      <div class="rounded border border-border bg-surface-800">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="border-b border-border bg-surface-700">
              <tr>
                <th class="px-4 py-3 text-left font-semibold text-text-primary">
                  <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="toggleSort('name')">
                    {{ t('common.item') }} <span class="text-xs text-text-tertiary">{{ sortIndicator('name') }}</span>
                  </button>
                </th>
                <th class="px-4 py-3 text-right font-semibold text-text-primary">
                  <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="toggleSort('buy_price')">
                    {{ t('bazaar.buyPrice') }} <span class="text-xs text-text-tertiary">{{ sortIndicator('buy_price') }}</span>
                  </button>
                </th>
                <th class="px-4 py-3 text-right font-semibold text-text-primary">
                  <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="toggleSort('sell_price')">
                    {{ t('bazaar.sellPrice') }} <span class="text-xs text-text-tertiary">{{ sortIndicator('sell_price') }}</span>
                  </button>
                </th>
                <th class="px-4 py-3 text-right font-semibold text-text-primary">
                  <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="toggleSort('margin')">
                    {{ t('common.margin') }} <span class="text-xs text-text-tertiary">{{ sortIndicator('margin') }}</span>
                  </button>
                </th>
                <th class="px-4 py-3 text-right font-semibold text-text-primary">
                  <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="toggleSort('hourly_instabuys')">
                    {{ t('common.volume1h') }} <span class="text-xs text-text-tertiary">{{ sortIndicator('hourly_instabuys') }}</span>
                  </button>
                </th>
                <th class="px-4 py-3 text-right font-semibold text-text-primary">
                  <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="toggleSort('coins_per_hour')">
                    {{ t('common.coinsPerHour') }} <span class="text-xs text-text-tertiary">{{ sortIndicator('coins_per_hour') }}</span>
                  </button>
                </th>
                <th class="px-4 py-3 text-center font-semibold text-text-primary">
                  {{ t('common.action') }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              <tr
                v-for="(item, index) in liveItems"
                :key="item.product_id"
                class="hover:bg-surface-700/50 relative"
                :class="[rowClass(item), isRestrictedFlip(index) ? 'vip-restricted' : '']"
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

                <td class="px-4 py-3 text-right text-text-secondary">
                  {{ formatCoins(item.buy_price) }}
                  <span class="ml-1 text-xs" :class="trendClass(buyTrend[item.product_id] || 'flat')">{{ trendIcon(buyTrend[item.product_id] || 'flat') }}</span>
                </td>

                <td class="px-4 py-3 text-right text-text-secondary">
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
                    class="rounded border border-border bg-surface-700 px-2 py-1 text-xs font-semibold text-text-primary hover:bg-surface-600"
                    @click="copyItemCommand(item.product_id)"
                  >
                    {{ t('bazaar.copyBz') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="liveItems.length === 0" class="px-4 py-8 text-center text-text-secondary">
          {{ t('bazaar.noFlips') }}
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="flex items-center justify-between">
        <div class="text-sm text-text-secondary">
          {{ t('common.page') }} {{ pagination.current_page }} {{ t('common.of') }} {{ pagination.last_page }} ({{ pagination.total }} {{ t('common.total') }})
        </div>
        <div class="flex gap-2">
          <button
            v-if="pagination.current_page > 1"
            @click="goToPage(1)"
            class="rounded border border-border bg-surface-700 px-3 py-2 text-sm text-text-primary hover:bg-surface-600"
          >
            {{ t('common.first') }}
          </button>
          <button
            v-if="pagination.current_page > 1"
            @click="goToPage(pagination.current_page - 1)"
            class="rounded border border-border bg-surface-700 px-3 py-2 text-sm text-text-primary hover:bg-surface-600"
          >
            {{ t('common.previous') }}
          </button>
          <button
            v-if="pagination.current_page < pagination.last_page"
            @click="goToPage(pagination.current_page + 1)"
            class="rounded border border-border bg-surface-700 px-3 py-2 text-sm text-text-primary hover:bg-surface-600"
          >
            {{ t('common.next') }}
          </button>
          <button
            v-if="pagination.current_page < pagination.last_page"
            @click="goToPage(pagination.last_page)"
            class="rounded border border-border bg-surface-700 px-3 py-2 text-sm text-text-primary hover:bg-surface-600"
          >
            {{ t('common.last') }}
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
  items: Object,
  best_picks: Object,
  top_flips: Array,
  ai_insights: Array,
  subscriptionFeatures: Object,
  filters: Object,
})

const tier = computed(() => String(props.subscriptionFeatures?.tier || 'free'))
const isVipOrHigher = computed(() => tier.value === 'vip' || tier.value === 'mvp')
const isMvp = computed(() => tier.value === 'mvp')

const isRestrictedFlip = (index) => !isVipOrHigher.value && index < 2

const pagination = ref({
  current_page: props.items.current_page,
  last_page: props.items.last_page,
  total: props.items.total,
})

const liveItems = ref([...props.items.data])
const buyTrend = ref({})
const sellTrend = ref({})

const search = ref(props.filters.search || '')
const sortBy = ref(props.filters.sort || 'coins_per_hour')
const sortDir = ref(props.filters.dir || 'desc')
const isRefreshing = ref(false)

const AUTO_REFRESH_INTERVAL_SECONDS = Number(props.subscriptionFeatures?.refresh_seconds || 180)
const autoRefreshRemainingSeconds = ref(AUTO_REFRESH_INTERVAL_SECONDS)

let debounceTimer = null
let echoChannel = null
let autoRefreshTickTimer = null

const COFLNET_ICON_BASE = 'https://sky.coflnet.com/static/icon/'
const SKYCRYPT_ICON_BASE = 'https://sky.shiiyu.moe/api/item/'
const DEFAULT_ITEM_TEXTURE = '/img/textures/chest.png'

watch(() => props.items, (newItems) => {
  liveItems.value = [...newItems.data]
  pagination.value = {
    current_page: newItems.current_page,
    last_page: newItems.last_page,
    total: newItems.total,
  }
})

function debouncedApplyFilters() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 300)
}

function applyFilters() {
  router.get(route('bazaar'), {
    search: search.value || undefined,
    sort: sortBy.value,
    dir: sortDir.value,
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
  if (sortBy.value !== column) return '↕'
  return sortDir.value === 'desc' ? '↓' : '↑'
}

function resetFilters() {
  search.value = ''
  sortBy.value = 'coins_per_hour'
  sortDir.value = 'desc'
  applyFilters()
}

function refreshMarket() {
  if (isRefreshing.value) return

  isRefreshing.value = true
  router.get(route('bazaar'), {
    search: search.value || undefined,
    sort: sortBy.value,
    dir: sortDir.value,
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
  router.get(route('bazaar'), {
    search: search.value || undefined,
    sort: sortBy.value,
    dir: sortDir.value,
    page,
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}

// --- Texture helpers ---
function getTextureUrl(productId) {
  return COFLNET_ICON_BASE + encodeURIComponent(String(productId || '').toUpperCase())
}

function getTextureFallbackUrl(productId) {
  const pid = String(productId || '').toUpperCase()
  return SKYCRYPT_ICON_BASE + encodeURIComponent(pid)
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

// --- Calculation helpers ---
function margin(item) {
  return (Number(item.buy_price || 0) * 0.9875) - Number(item.sell_price || 0)
}

function marginPercent(item) {
  const buy = Number(item.buy_price || 0)
  if (buy <= 0) return 0
  return (margin(item) / buy) * 100
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

// --- Formatting ---
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

// --- Trend (WebSocket live updates) ---
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

// --- Row styling ---
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

// --- Clipboard ---
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

// --- Lifecycle ---
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
tr.vip-restricted {
  filter: blur(5px);
  position: relative;
  opacity: 0.6;
}

tr.vip-restricted::after {
  content: 'VIP/MVP Only';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: rgba(0, 0, 0, 0.8);
  color: rgb(96, 165, 250);
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
  z-index: 20;
  pointer-events: none;
  border: 1px solid rgb(96, 165, 250);
}

tr.vip-restricted:hover {
  filter: blur(5px);
}
</style>
