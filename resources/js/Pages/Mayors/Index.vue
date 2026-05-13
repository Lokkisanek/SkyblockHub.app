<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/strings/useI18n';

const { t } = useI18n();

const props = defineProps({
    mayors: Array,
    currentMayorName: String,
});

const openMayor = ref(null);

function toggleMayor(name) {
    openMayor.value = openMayor.value === name ? null : name;
}

function perkCountLabel(mayor) {
    const count = Array.isArray(mayor?.perks) ? mayor.perks.length : 0;
    return count === 1 ? t('mayors.perkSingular') : t('mayors.perkPlural', { count });
}

/** Full-body skin image served from public/img/mayors/<Name>.png */
function skinImageUrl(mayor) {
    const file = mayor?.skin_name;
    if (file) return `/img/mayors/${encodeURIComponent(file)}.png`;
    return null;
}

/** Head thumbnail — use local skin PNG cropped to head area */
function mayorHeadUrl(mayor) {
    const file = mayor?.skin_name;
    if (file) return `/img/mayors/${encodeURIComponent(file)}.png`;
    return null;
}

const CATEGORY_ORDER = ['regular', 'special', 'one-off'];
const CATEGORY_LABELS = computed(() => ({
    regular:  t('mayors.regularCandidates'),
    special:  t('mayors.specialCandidates'),
    'one-off': t('mayors.oneOffCandidates'),
}));

/** Group mayors by category preserving API order within each group */
const mayorsByCategory = computed(() => {
    const groups = { regular: [], special: [], 'one-off': [] };
    for (const mayor of (props.mayors ?? [])) {
        const cat = mayor.category ?? 'regular';
        if (!groups[cat]) groups[cat] = [];
        groups[cat].push(mayor);
    }
    return CATEGORY_ORDER.map(cat => ({
        key: cat,
        label: CATEGORY_LABELS.value[cat],
        mayors: groups[cat] ?? [],
    })).filter(g => g.mayors.length > 0);
});
</script>

<template>
    <Head :title="t('mayors.title')" />
    <AuthenticatedLayout>
        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h1 class="mb-6 text-lg font-bold text-white">{{ t('mayors.title') }}</h1>

                <!-- Category sections -->
                <div v-for="group in mayorsByCategory" :key="group.key" class="mb-10">
                    <div class="category-heading">
                        <span class="category-heading-inner">{{ group.label }}</span>
                    </div>

                    <div class="mayor-grid">
                        <button
                            v-for="mayor in group.mayors"
                            :key="mayor.name"
                            type="button"
                            class="mayor-row"
                            :class="{ 'mayor-card--active': mayor.is_active }"
                            @click="toggleMayor(mayor.name)"
                        >
                            <!-- Expanded: full-body skin image left + info right -->
                            <template v-if="openMayor === mayor.name">
                                <div class="mayor-expanded">
                                    <div class="mayor-skin-wrap">
                                        <img
                                            v-if="skinImageUrl(mayor)"
                                            :src="skinImageUrl(mayor)"
                                            :alt="`${mayor.label ?? mayor.name} skin`"
                                            class="mayor-skin-img"
                                        >
                                        <img
                                            v-else
                                            :src="mayorHeadUrl(mayor)"
                                            :alt="`${mayor.label ?? mayor.name} head`"
                                            class="mayor-head-fallback"
                                        >
                                    </div>

                                    <div class="mayor-content">
                                        <div class="mayor-summary-row">
                                            <div class="min-w-0 flex-1 text-left">
                                                <div class="text-base font-semibold text-white leading-tight">{{ mayor.label ?? mayor.name }}</div>
                                                <div class="text-xs text-neutral mt-0.5">{{ perkCountLabel(mayor) }}</div>
                                            </div>
                                            <span
                                                v-if="mayor.is_active"
                                                class="ml-3 shrink-0 rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-semibold text-emerald-400"
                                            >{{ t('mayors.active') }}</span>
                                            <svg
                                                class="ml-3 h-4 w-4 shrink-0 text-white/60 transition-transform rotate-180"
                                                viewBox="0 0 20 20" fill="currentColor"
                                            >
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </div>

                                        <div class="mayor-details">
                                            <div v-if="mayor.summary" class="mayor-summary-text">{{ mayor.summary }}</div>

                                            <div v-if="mayor.perks?.length" class="space-y-3">
                                                <div v-for="(perk, i) in mayor.perks" :key="i" class="perk-block">
                                                    <div class="perk-name">{{ perk.name }}</div>
                                                    <div v-if="perk.description" class="perk-description">{{ perk.description }}</div>
                                                </div>
                                            </div>
                                            <div v-else class="text-xs text-neutral">{{ t('mayors.noPerkData') }}</div>

                                            <div class="mt-3 border-t border-white/10 pt-2 text-[10px] text-neutral">
                                                <template v-if="mayor.last_elected">{{ t('mayors.lastElected', { date: mayor.last_elected }) }}</template>
                                                <template v-else>{{ t('mayors.noElectionData') }}</template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Collapsed: head + name row -->
                            <template v-else>
                                <div class="mayor-row-header">
                                    <img
                                        :src="mayorHeadUrl(mayor)"
                                        :alt="`${mayor.label ?? mayor.name} head`"
                                        class="mayor-head"
                                    >
                                    <div class="mayor-content">
                                        <div class="mayor-summary-row">
                                            <div class="min-w-0 flex-1 text-left">
                                                <div class="truncate text-base font-semibold text-white">{{ mayor.label ?? mayor.name }}</div>
                                                <div class="text-xs text-neutral">{{ perkCountLabel(mayor) }}</div>
                                            </div>
                                            <span
                                                v-if="mayor.is_active"
                                                class="ml-3 shrink-0 rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-semibold text-emerald-400"
                                            >{{ t('mayors.active') }}</span>
                                            <svg
                                                class="ml-3 h-4 w-4 shrink-0 text-white/60 transition-transform"
                                                viewBox="0 0 20 20" fill="currentColor"
                                            >
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </button>
                    </div>
                </div>

                <div v-if="!mayors?.length" class="rounded-lg border border-white/10 bg-white/5 p-8 text-center text-sm text-neutral">
                    {{ t('mayors.noMayorData') }}
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* ── Category headings ─────────────────────────────────────────────────── */
.category-heading {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
}

.category-heading::before,
.category-heading::after {
    content: '';
    flex: 1;
    height: 1px;
    background: rgba(255, 255, 255, 0.08);
}

.category-heading-inner {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.35);
    white-space: nowrap;
}

/* ── Card grid ─────────────────────────────────────────────────────────── */
.mayor-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

@media (max-width: 1023px) {
    .mayor-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 639px) {
    .mayor-grid { grid-template-columns: 1fr; }
}

/* ── Card base ─────────────────────────────────────────────────────────── */
.mayor-row {
    width: 100%;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: linear-gradient(180deg, rgba(14, 14, 14, 0.96) 0%, rgba(13, 13, 13, 0.9) 100%);
    backdrop-filter: blur(10px);
    transition: border-color 0.2s, background 0.2s;
    text-align: left;
    align-self: start;
}

.mayor-row:hover {
    border-color: rgba(255, 255, 255, 0.16);
    background: linear-gradient(180deg, rgba(18, 18, 18, 0.97) 0%, rgba(16, 16, 16, 0.92) 100%);
}

.mayor-card--active {
    border-color: rgba(52, 211, 153, 0.4);
    box-shadow: 0 0 18px rgba(52, 211, 153, 0.06);
}

.mayor-card--active:hover {
    border-color: rgba(52, 211, 153, 0.55);
}

/* ── Collapsed row ─────────────────────────────────────────────────────── */
.mayor-row-header {
    display: flex;
    align-items: center;
    padding: 10px 12px;
    gap: 10px;
}

.mayor-head {
    width: 48px;
    height: 48px;
    border-radius: 4px;
    image-rendering: pixelated;
    border: 1px solid rgba(255, 255, 255, 0.08);
    flex-shrink: 0;
    object-fit: cover;
    object-position: top center;
}

/* ── Expanded layout ───────────────────────────────────────────────────── */
.mayor-expanded {
    display: flex;
    align-items: stretch;
    gap: 0;
    min-height: 200px;
}

.mayor-skin-wrap {
    flex-shrink: 0;
    width: 110px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px 0 8px 8px;
}

.mayor-skin-img {
    width: 100px;
    height: auto;
    image-rendering: pixelated;
    object-fit: contain;
}

.mayor-head-fallback {
    width: 64px;
    height: 64px;
    border-radius: 4px;
    image-rendering: pixelated;
    margin: 16px auto;
}

/* ── Shared content ────────────────────────────────────────────────────── */
.mayor-content {
    min-width: 0;
    flex: 1;
    padding: 10px 12px 10px 8px;
}

.mayor-summary-row {
    display: flex;
    align-items: flex-start;
}

.mayor-details {
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    margin-top: 10px;
    padding-top: 10px;
}

.mayor-summary-text {
    margin-bottom: 12px;
    color: #bdbdbd;
    font-size: 12px;
    line-height: 1.35rem;
}

.perk-block {
    font-family: 'Courier New', monospace;
    letter-spacing: 0.15px;
}

.perk-name {
    color: #e879f9;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 2px;
    text-shadow: 0 0 8px rgba(232, 121, 249, 0.15);
}

.perk-description {
    color: #c7c7c7;
    font-size: 12px;
    line-height: 1.25rem;
}
</style>
