<script setup>
import { ref, computed, onMounted, provide } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/strings/useI18n';
import { preloadAllTextures, setEnabledPacks, getSkinUrl, getHeadUrl, getRarityColor, getItemTextureUrl, RARITY_COLORS, SKILL_ICONS, SLAYER_ICONS, CLASS_ICONS } from '@/utils/textures';
import ItemSlot from '@/Components/SkyBlock/ItemSlot.vue';
import InventoryGrid from '@/Components/SkyBlock/InventoryGrid.vue';
import PackSelector from '@/Components/SkyBlock/PackSelector.vue';
import PlayerModel from '@/Components/SkyBlock/PlayerModel.vue';

const { t } = useI18n();

const props = defineProps({
    minecraftUsername: { type: String, default: null },
});

const username = ref(props.minecraftUsername || '');
const profileData = ref(null);
const loading = ref(false);
const error = ref('');
const selectedProfile = ref(null);

const hasLoadedProfile = computed(
    () => !!(profileData.value && selectedProfile.value && currentProfile.value && !loading.value)
);

const showHeroLoading = computed(() => loading.value && !profileData.value);
const activeTab = ref('gear');
let activeFetchController = null;
let fetchSequence = 0;

/* ── Tab definitions ─────────────────────────────────────── */
const tabs = computed(() => [
    { id: 'gear',        name: t('profileStats.tabGear') },
    { id: 'accessories', name: t('profileStats.tabAccessories') },
    { id: 'inventory',   name: t('profileStats.tabInventory') },
    { id: 'pets',        name: t('profileStats.tabPets') },
    { id: 'skills',      name: t('profileStats.tabSkills') },
    { id: 'dungeons',    name: t('profileStats.tabDungeons') },
    { id: 'slayer',      name: t('profileStats.tabSlayer') },
    { id: 'collections', name: t('profileStats.tabCollections') },
]);

/* ── Inventory sub-tabs with SkyBlock UI style icons ────────── */
const inventorySubTabs = computed(() => {
    const headIcon = profileData.value?.uuid
        ? `https://mc-heads.net/avatar/${profileData.value.uuid}/24`
        : null;
    return [
        { id: 'inv',              name: t('profileStats.subInventory'),      icon: headIcon },
        { id: 'backpack',         name: t('profileStats.subBackpack'),       icon: '/img/textures/chest.png' },
        { id: 'enderchest',       name: t('profileStats.subEnderchest'),     icon: '/img/textures/ender_chest.png' },
        { id: 'personal_vault',   name: t('profileStats.subPersonalVault'),   icon: '/img/textures/chest.png' },
        { id: 'talisman_bag',     name: t('profileStats.subTalismanBag'),     icon: '/img/textures/ender_eye.png' },
        { id: 'potion_bag',       name: t('profileStats.subPotionBag'),       icon: '/img/textures/potion_bottle_drinkable.png' },
        { id: 'fishing_bag',      name: t('profileStats.subFishingBag'),      icon: '/img/textures/fishing_rod_uncast.png' },
        { id: 'quiver',           name: t('profileStats.subQuiver'),          icon: '/img/textures/arrow.png' },
        { id: 'museum',           name: t('profileStats.subMuseum'),          icon: '/img/textures/gold_ingot.png' },
        { id: 'rift_inventory',   name: t('profileStats.subRiftInventory'),   icon: '/img/textures/ender_pearl.png' },
        { id: 'rift_enderchest',  name: t('profileStats.subRiftEnderchest'),  icon: '/img/textures/ender_chest.png' },
    ];
});

const activeInventorySubTab = ref('inv');
const expandedBackpack = ref(null);   // index of opened backpack (null = all collapsed)
const expandedEnderPage = ref(null);  // index of opened enderchest page
const expandedRiftEnderPage = ref(null); // index of opened rift enderchest page

/* ── Pets collapsible state ────────────────────────────────── */
const showMorePets = ref(false);
const showMissingPets = ref(false);

/* ── Pets computed ─────────────────────────────────────────── */
const activePet = computed(() => currentData.value?.pets?.pets?.find(p => p.active) ?? null);
const displayUniquePets = computed(() => {
    const unique = currentData.value?.pets?.uniquePets ?? [];
    return unique.filter(p => !p.active);
});

/* ── Texture pack version key ──────────────────────────────── */
const textureVersion = ref(0);
provide('textureVersion', textureVersion);

async function onPacksChanged(packIds) {
    await setEnabledPacks(packIds);
    textureVersion.value++;
}

const petTierColors = {
    COMMON:    '#AAAAAA',
    UNCOMMON:  '#55FF55',
    RARE:      '#5555FF',
    EPIC:      '#AA00AA',
    LEGENDARY: '#FFAA00',
    MYTHIC:    '#FF55FF',
};

/* ── API fetch (Hypixel-backed profile JSON) ─────────────── */
async function fetchProfile() {
    const name = username.value.trim();
    if (!name) return;

    if (activeFetchController) {
        activeFetchController.abort();
    }

    const requestId = ++fetchSequence;
    const controller = new AbortController();
    activeFetchController = controller;
    const timeoutId = setTimeout(() => controller.abort(), 25000);

    loading.value = true;
    error.value = '';
    profileData.value = null;
    selectedProfile.value = null;

    try {
        const res = await fetch(`/api/profile/minecraft/${encodeURIComponent(name)}`, {
            signal: controller.signal,
        });
        const json = await res.json();

        if (requestId !== fetchSequence) {
            return;
        }

        if (!res.ok) {
            error.value = json.error || t('profileStats.fetchFailed');
            return;
        }

        const profiles = json.data?.profiles ?? {};
        const keys = Object.keys(profiles);

        if (keys.length === 0) {
            error.value = json.error || t('profileStats.fetchFailed');
            profileData.value = null;
            selectedProfile.value = null;
            return;
        }

        profileData.value = json.data;

        const sel = keys.find((k) => profiles[k].selected) || keys[0];
        if (sel) selectedProfile.value = sel;
    } catch (e) {
        if (requestId !== fetchSequence) {
            return;
        }

        if (e?.name === 'AbortError') {
            error.value = t('profileStats.requestTimeout');
        } else {
            error.value = t('profileStats.networkError');
        }
    } finally {
        clearTimeout(timeoutId);

        if (requestId === fetchSequence) {
            loading.value = false;
            activeFetchController = null;
        }
    }
}

/* ── Computed ─────────────────────────────────────────────── */
const currentProfile = computed(() => {
    if (!profileData.value || !selectedProfile.value) return null;
    return profileData.value.profiles?.[selectedProfile.value];
});

const currentData = computed(() => currentProfile.value?.data ?? null);

const skinUrl = computed(() => getSkinUrl(profileData.value?.uuid));
const headUrl = computed(() => getHeadUrl(profileData.value?.uuid, 64));

// ── Rank display helpers ─────────────────────────────────
const rankPlusText = computed(() => {
    const prefix = profileData.value?.rank?.prefix;
    if (!prefix || !profileData.value?.rank?.plusColor) return '';
    const match = prefix.match(/(\+{1,2})/);
    return match ? match[1] : '';
});

const rankTextBefore = computed(() => {
    const prefix = profileData.value?.rank?.prefix;
    if (!prefix) return '';
    const plusMatch = prefix.match(/(\+{1,2})/);
    if (!plusMatch) return prefix + ' ';
    return prefix.substring(0, plusMatch.index);
});

const rankTextAfter = computed(() => {
    const prefix = profileData.value?.rank?.prefix;
    if (!prefix) return '';
    const plusMatch = prefix.match(/(\+{1,2})/);
    if (!plusMatch) return '';
    const afterPlus = prefix.substring(plusMatch.index + plusMatch[1].length);
    return afterPlus ? afterPlus + ' ' : '] ';
});

// ── Skills ──────────────────────────────────────────────
const mainSkillNames = ['farming', 'mining', 'combat', 'foraging', 'fishing', 'enchanting'];
const secondarySkillNames = ['alchemy', 'carpentry', 'taming', 'runecrafting', 'social', 'hunting'];

const allSkills = computed(() => {
    if (!currentData.value?.skills) return [];
    return [...mainSkillNames, ...secondarySkillNames]
        .filter(n => currentData.value.skills[n])
        .map(n => ({ name: n, ...currentData.value.skills[n] }));
});

const leftSkills = computed(() => allSkills.value.filter((_, i) => i % 2 === 0));
const rightSkills = computed(() => allSkills.value.filter((_, i) => i % 2 === 1));

// ── Gear: Armor stats sum ───────────────────────────────
const armorStats = computed(() => {
    const armor = currentData.value?.armor ?? [];
    const totals = {};
    for (const item of armor) {
        if (!item?.stats) continue;
        for (const [key, stat] of Object.entries(item.stats)) {
            if (!totals[key]) totals[key] = { value: 0, percent: stat.percent };
            totals[key].value += stat.value;
        }
    }
    return totals;
});

const equipmentStats = computed(() => {
    const equip = currentData.value?.equipment ?? [];
    const totals = {};
    for (const item of equip) {
        if (!item?.stats) continue;
        for (const [key, stat] of Object.entries(item.stats)) {
            if (!totals[key]) totals[key] = { value: 0, percent: stat.percent };
            totals[key].value += stat.value;
        }
    }
    return totals;
});

// ── Accessories ─────────────────────────────────────────
const accessoryStats = computed(() => {
    const items = currentData.value?.accessories ?? [];
    const unique = new Set();
    let recombed = 0;
    for (const item of items) {
        if (item?.skyblock_id) unique.add(item.skyblock_id);
        if (item?.recombobulated) recombed++;
    }
    return {
        total: items.length,
        unique: unique.size,
        recombobulated: recombed,
    };
});

const accessoryBag = computed(() => currentData.value?.accessory_bag_storage ?? {});

// ── Active weapon ───────────────────────────────────────
const activeWeapon = computed(() => {
    const weapons = currentData.value?.weapons ?? [];
    return weapons.length > 0 ? weapons[0] : null;
});

// ── Inventory sub-tab data ──────────────────────────────
const inventorySubTabData = computed(() => {
    if (!currentData.value) return [];
    const tab = activeInventorySubTab.value;
    switch (tab) {
        case 'inv':              return currentData.value.inventory ?? [];
        case 'enderchest':       return [];  // handled separately with pages
        case 'personal_vault':   return currentData.value.personal_vault ?? [];
        case 'talisman_bag':     return currentData.value.talisman_bag ?? [];
        case 'potion_bag':       return currentData.value.potion_bag ?? [];
        case 'fishing_bag':      return currentData.value.fishing_bag ?? [];
        case 'quiver':           return currentData.value.quiver ?? [];
        case 'backpack':         return [];  // handled separately
        case 'museum':           return [];  // handled separately
        case 'rift_inventory':   return currentData.value.rift_inventory ?? [];
        case 'rift_enderchest':  return [];  // handled separately with pages
        default:                 return [];
    }
});

const backpackStorage = computed(() => currentData.value?.storage ?? []);
const enderchestPages = computed(() => {
    const raw = currentData.value?.enderchest;
    if (!raw || !Array.isArray(raw)) return [];
    // Defensive: if the API returned a flat array of items instead of pages, skip
    if (raw.length > 0 && !('items' in raw[0])) return [];
    return raw;
});
const riftEnderchestPages = computed(() => {
    const raw = currentData.value?.rift_enderchest;
    if (!raw || !Array.isArray(raw) || raw.length === 0) return [];
    // Split flat array into pages of 45 slots (5 rows × 9 cols)
    const slotsPerPage = 45;
    const pages = [];
    for (let i = 0; i < raw.length; i += slotsPerPage) {
        const pageSlots = raw.slice(i, i + slotsPerPage);
        pages.push({
            page: pages.length,
            items: pageSlots,
            count: pageSlots.filter(s => s !== null).length,
        });
    }
    return pages;
});
const museumData = computed(() => currentData.value?.museum ?? {});

/* ── Slayer computed data (new structure: { slayers, total_slayer_xp, total_coins_spent }) ── */
const slayerData = computed(() => currentData.value?.slayers ?? { slayers: {}, total_slayer_xp: 0, total_coins_spent: 0 });

/* ── Collections computed data ── */
const collectionsData = computed(() => currentData.value?.collections ?? { categories: {}, totalCollections: 0, maxedCollections: 0 });

const ROMAN = ['', 'I', 'II', 'III', 'IV', 'V'];
function romanNumeral(n) { return ROMAN[n] || String(n); }

const COLLECTION_CATEGORY_ICONS = {
    FARMING: '🌾', MINING: '⛏️', COMBAT: '⚔️', FORAGING: '🌲', FISHING: '🎣', RIFT: '🌀', BOSS: '💀',
};

function slayerMaxTier(key) {
    return key === 'vampire' ? 5 : 5;
}

function formatSlayerXP(slayer) {
    const lvl = slayer.level;
    if (!lvl) return `0 ${t('profileStats.xp')}`;
    if (lvl.currentLevel >= lvl.maxLevel) return `${Number(lvl.xp).toLocaleString()} ${t('profileStats.xp')}`;
    return `${Number(lvl.xpCurrent).toLocaleString()} / ${Number(lvl.xpForNext).toLocaleString()} ${t('profileStats.xp')}`;
}

/* ── Formatting helpers ──────────────────────────────────── */
function fNum(num, decimals = 2) {
    if (num === null || num === undefined) return '—';
    const abs = Math.abs(num);
    if (abs >= 1e9) return (num / 1e9).toFixed(decimals) + 'B';
    if (abs >= 1e6) return (num / 1e6).toFixed(decimals) + 'M';
    if (abs >= 1e3) return (num / 1e3).toFixed(decimals) + 'K';
    if (Number.isInteger(num)) return num.toLocaleString();
    return num.toFixed(decimals);
}

function formatXP(skill) {
    if (skill.level >= skill.maxLevel) return `${fNum(skill.xp)} ${t('profileStats.xp')}`;
    return `${fNum(skill.xpCurrent)} / ${fNum(skill.xpForNext)} ${t('profileStats.xp')}`;
}

/** Vanilla `/item/...` paths for Profile Stats skill icons (ring). */
const SKILL_TEXTURE_PATHS = {
    farming: '/item/golden_hoe',
    mining: '/item/iron_pickaxe',
    combat: '/item/diamond_sword',
    foraging: '/item/diamond_axe',
    fishing: '/item/fishing_rod_uncast',
    enchanting: '/item/book_enchanted',
    alchemy: '/item/brewing_stand',
    carpentry: '/item/iron_axe',
    taming: '/item/bone',
    runecrafting: '/item/ender_eye',
    social: '/item/cake',
    hunting: '/item/feather',
};

const SB_LEVEL_TEXTURE_ITEM = { texture_path: '/item/experience_bottle' };

function skillTextureItem(skillName) {
    const path = SKILL_TEXTURE_PATHS[skillName];
    return path ? { texture_path: path } : null;
}

function skillTextureUrl(skillName) {
    return getItemTextureUrl(skillTextureItem(skillName));
}

/** Pill fill modifier classes for the Skills tab (green in progress, orange when maxed — matches in-game list UI). */
function skillPillFillClass(skill) {
    if (skill.level >= skill.maxLevel) return 'ps-skill-pill-fill--max';
    return '';
}

function formatSkyblockLevelXP(sb) {
    if (!sb) return '';
    return `${fNum(sb.xpCurrent)} / ${fNum(sb.xpForNext)} ${t('profileStats.xp')}`;
}

function timeAgo(ts) {
    if (!ts) return '—';
    const ms = Date.now() - ts;
    const years = Math.floor(ms / (365.25 * 24 * 60 * 60 * 1000));
    if (years >= 1) return years > 1 ? t('profileStats.yearsAgo', { count: years }) : t('profileStats.yearAgo', { count: years });
    const months = Math.floor(ms / (30.44 * 24 * 60 * 60 * 1000));
    if (months >= 1) return months > 1 ? t('profileStats.monthsAgo', { count: months }) : t('profileStats.monthAgo', { count: months });
    const days = Math.floor(ms / (24 * 60 * 60 * 1000));
    if (days >= 1) return days > 1 ? t('profileStats.daysAgo', { count: days }) : t('profileStats.dayAgo', { count: days });
    const hours = Math.floor(ms / (60 * 60 * 1000));
    return hours > 1 ? t('profileStats.hoursAgo', { count: hours }) : t('profileStats.hourAgo', { count: hours });
}

function capitalize(s) { return s.charAt(0).toUpperCase() + s.slice(1); }
function petName(t) { return t.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, c => c.toUpperCase()); }

/** Minimal item shape for {@link getItemTextureUrl} from API `texture_path`. */
function collectionTextureItem(item) {
    return item?.texture_path ? { texture_path: item.texture_path } : null;
}

// ── Dungeon helpers ──
function formatDungeonXP(level) {
    if (!level) return `0 ${t('profileStats.xp')}`;
    if (level.level >= (level.maxLevel || 50)) return `${fNum(level.xp)} ${t('profileStats.xp')}`;
    return `${fNum(level.xpCurrent)} / ${fNum(level.xpForNext)} ${t('profileStats.xp')}`;
}

function formatDungeonClassXP(cls) {
    if (!cls) return `0 ${t('profileStats.xp')}`;
    if (cls.level >= (cls.maxLevel || 50)) return `${fNum(cls.xp)} ${t('profileStats.xp')}`;
    return `${fNum(cls.xpCurrent)} / ${fNum(cls.xpForNext)} ${t('profileStats.xp')}`;
}

function dungeonLevelClass(level) {
    if (!level) return '';
    if (level.level >= (level.maxLevel || 50)) return 'skill-level-max';
    if (level.level >= 40) return 'skill-level-gold';
    return '';
}

function dungeonBarClass(level) {
    if (!level) return '';
    if (level.level >= (level.maxLevel || 50)) return 'skill-bar-fill-max';
    if (level.level >= 40) return 'skill-bar-fill-gold';
    return '';
}

function dungeonClassLevelClass(cls, name) {
    if (!cls) return '';
    const selected = currentData.value?.dungeons?.selected_class === name;
    if (cls.level >= (cls.maxLevel || 50)) return 'skill-level-max';
    if (cls.level >= 40) return 'skill-level-gold';
    if (selected) return 'skill-level-selected';
    return '';
}

function dungeonClassBarClass(cls) {
    if (!cls) return '';
    if (cls.level >= (cls.maxLevel || 50)) return 'skill-bar-fill-max';
    if (cls.level >= 40) return 'skill-bar-fill-gold';
    return '';
}

const allClassesMaxed = computed(() => {
    const classes = currentData.value?.dungeons?.classes;
    if (!classes) return false;
    return Object.values(classes).every(c => c.level >= (c.maxLevel || 50));
});

function formatStatName(key) {
    return key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function formatFloorStat(key, val) {
    if (key.startsWith('fastest_time')) {
        const totalSec = val / 1000;
        const min = Math.floor(totalSec / 60);
        const sec = (totalSec % 60).toFixed(1);
        return `${min}:${sec.padStart(4, '0')}`;
    }
    if (typeof val === 'number') return val.toLocaleString();
    return val;
}

function formatElapsed(ms) {
    const totalSec = ms / 1000;
    const min = Math.floor(totalSec / 60);
    const sec = Math.floor(totalSec % 60);
    return `${String(min).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
}

function formatStat(key, stat) {
    const v = stat.value % 1 === 0 ? stat.value : stat.value.toFixed(1);
    return `${v}${stat.percent ? '%' : ''} ${key}`;
}

const statColors = {
    CC: '#FF5555', CD: '#FF5555', Str: '#FF5555', Dmg: '#FF5555',
    HP: '#FF5555', HPR: '#55FF55', Def: '#55FF55', TD: '#FFFFFF',
    Int: '#55FFFF', Spd: '#FFFFFF', AS: '#FFFF55', FS: '#FF5555',
    MF: '#55FFFF', SCC: '#55FFFF', PL: '#FF55FF',
};

function getStatColor(key) { return statColors[key] ?? '#AAAAAA'; }

onMounted(async () => {
    preloadAllTextures();
    if (username.value) fetchProfile();
});
</script>

<template>
    <Head :title="t('profileStats.title')" />

    <AuthenticatedLayout>
        <div :class="hasLoadedProfile ? 'py-4' : ''">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex w-full flex-col">
                    <!-- Top spacer: height animates (flex-grow does not interpolate in browsers). -->
                    <div
                        aria-hidden="true"
                        class="shrink-0 overflow-hidden transition-[height,opacity] duration-1000 ease-out motion-reduce:transition-none"
                        :class="
                            hasLoadedProfile
                                ? 'pointer-events-none h-0 opacity-0'
                                : 'h-[min(36vh,300px)] opacity-100'
                        "
                    />

                    <div class="z-10 flex w-full shrink-0 justify-center" :class="hasLoadedProfile ? 'mb-8' : ''">
                        <div class="w-full max-w-2xl rounded-2xl border border-border/80 bg-surface-900/75 p-3 shadow-[0_16px_40px_rgba(0,0,0,0.35)] backdrop-blur-sm">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <div class="relative flex-1">
                                    <svg class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M8.5 3a5.5 5.5 0 104.35 8.87l2.64 2.64a1 1 0 001.42-1.42l-2.64-2.64A5.5 5.5 0 008.5 3zm-3.5 5.5a3.5 3.5 0 117 0 3.5 3.5 0 01-7 0z" clip-rule="evenodd" />
                                    </svg>
                                    <input
                                        v-model="username"
                                        type="text"
                                        :placeholder="t('profileStats.searchPlaceholder')"
                                        class="w-full rounded-xl border border-border/80 bg-surface-800/80 py-3 pl-11 pr-4 text-sm text-white placeholder:text-neutral/80 transition focus:border-profit/70 focus:outline-none focus:ring-2 focus:ring-profit/25"
                                        @keyup.enter="fetchProfile"
                                    />
                                </div>
                                <button
                                    type="button"
                                    @click="fetchProfile"
                                    :disabled="loading"
                                    class="inline-flex h-[46px] items-center justify-center rounded-xl border border-profit/35 bg-profit/20 px-6 text-sm font-semibold text-profit transition hover:bg-profit/30 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {{ loading ? t('profileStats.loading') : t('profileStats.search') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="error"
                        class="mx-auto mb-4 w-full max-w-2xl shrink-0 rounded-lg border border-loss/50 bg-loss/10 px-4 py-3 text-center text-sm text-loss"
                        :class="hasLoadedProfile ? '' : 'mt-3'"
                    >
                        {{ error }}
                    </div>

                    <div
                        v-if="!hasLoadedProfile"
                        class="flex shrink-0 flex-col items-center justify-center gap-4 py-6"
                        :class="showHeroLoading ? 'min-h-[4.5rem]' : 'min-h-0'"
                    >
                        <div v-if="showHeroLoading" class="flex items-center gap-3 text-neutral" role="status" aria-live="polite">
                            <span class="h-5 w-5 shrink-0 animate-spin rounded-full border-2 border-neutral/40 border-t-profit" />
                            <span class="text-sm text-neutral">{{ t('profileStats.fetchingProfile') }}</span>
                        </div>
                    </div>

                    <div
                        aria-hidden="true"
                        class="shrink-0 overflow-hidden transition-[height,opacity] duration-1000 ease-out motion-reduce:transition-none"
                        :class="
                            hasLoadedProfile
                                ? 'pointer-events-none h-0 opacity-0'
                                : 'h-[min(36vh,300px)] opacity-100'
                        "
                    />

                    <!-- ════════════════════════════════════════════════════════ -->
                    <!--  PROFILE CONTENT                                        -->
                    <!-- ════════════════════════════════════════════════════════ -->
                    <div v-if="hasLoadedProfile" class="w-full">

                    <!-- ═══ STATS SUMMARY BAR ═══ -->
                    <div v-if="currentData" class="border border-profit/30 bg-profit/5 rounded px-3 py-2 mb-4 sm:px-4">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] sm:gap-x-4 sm:text-xs">
                            <span class="text-neutral">{{ t('profileStats.joined') }} <b class="text-white">{{ timeAgo(currentData.first_join) }}</b></span>
                            <span class="text-border-light">·</span>
                            <span class="text-neutral">{{ t('profileStats.purse') }} <b class="text-rarity-legendary">{{ fNum(currentData.networth?.purse) }} {{ t('profileStats.coins') }}</b></span>
                            <span class="text-border-light">·</span>
                            <span class="text-neutral">{{ t('profileStats.bank') }} <b class="text-rarity-legendary">{{ fNum(currentData.networth?.bank) }} {{ t('profileStats.coins') }}</b></span>
                            <span class="text-border-light">·</span>
                            <span class="text-neutral">{{ t('profileStats.skillAvg') }} <b class="text-white">{{ currentData.average_skill_level }}</b></span>
                            <span class="text-border-light">·</span>
                            <span class="text-neutral">{{ t('profileStats.fairySouls') }} <b class="text-white">{{ currentData.fairy_souls ?? '—' }} {{ t('profileStats.fairySoulsMax') }}</b></span>
                            <span class="text-border-light">·</span>
                            <span class="text-neutral">{{ t('profileStats.networth') }} <b class="text-rarity-legendary">{{ fNum(currentData.networth?.networth) }}</b></span>
                        </div>
                    </div>

                    <!-- ═══ PROFILE SELECTOR + HEADER ═══ -->
                    <div class="mb-3 flex items-center gap-4 flex-wrap">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <button v-for="(profile, key) in profileData.profiles" :key="key"
                                @click="selectedProfile = key"
                                class="px-3 py-1 text-xs rounded border transition"
                                :class="selectedProfile === key
                                    ? 'border-profit text-profit bg-profit/10'
                                    : 'border-border text-neutral hover:text-white hover:border-border-light'">
                                {{ profile.cute_name || key }}
                                <span v-if="profile.selected" class="ml-1 text-[10px] text-profit">●</span>
                            </button>
                        </div>
                        <div class="flex items-center gap-1.5 ml-auto">
                            <PackSelector @update:packs="onPacksChanged" />
                        </div>
                    </div>

                    <!-- ═══ MAIN TAB NAVIGATION (SkyBlock UI style underline) ═══ -->
                    <div class="flex border-b border-border mb-6 overflow-x-auto overscroll-x-contain -mx-1 px-1 sm:mx-0 sm:px-0">
                        <button v-for="tab in tabs" :key="tab.id"
                            @click="activeTab = tab.id"
                            class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider whitespace-nowrap border-b-2 transition"
                            :class="activeTab === tab.id
                                ? 'border-profit text-profit'
                                : 'border-transparent text-neutral hover:text-white'">
                            {{ tab.name }}
                        </button>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  GEAR TAB (SkyBlock UI style)                         -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'gear'">
                        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
                            <!-- Desktop: sticky 3D model -->
                            <div class="hidden lg:block w-52 shrink-0">
                                <div class="sticky top-20">
                                    <div class="player-name-rank">
                                        <template v-if="profileData?.rank?.prefix">
                                            <span class="rank-prefix" :style="{ color: profileData.rank.color }">
                                                {{ rankTextBefore }}<!--
                                            --><span v-if="rankPlusText" class="rank-plus" :style="{ color: profileData.rank.plusColor }">{{ rankPlusText }}</span><!--
                                            --><span v-if="rankTextAfter" :style="{ color: profileData.rank.color }">{{ rankTextAfter }}</span>
                                            </span>
                                            <span class="player-username" :style="{ color: profileData.rank.color }">{{ profileData.username }}</span>
                                        </template>
                                        <span v-else class="player-username" style="color: #AAAAAA">{{ profileData?.username }}</span>
                                    </div>
                                    <PlayerModel :uuid="profileData?.uuid" :width="208" :height="400" />
                                </div>
                            </div>

                            <!-- Mobile / tablet: compact model above gear -->
                            <div class="flex flex-col items-center lg:hidden">
                                <div class="player-name-rank">
                                    <template v-if="profileData?.rank?.prefix">
                                        <span class="rank-prefix" :style="{ color: profileData.rank.color }">
                                            {{ rankTextBefore }}<!--
                                        --><span v-if="rankPlusText" class="rank-plus" :style="{ color: profileData.rank.plusColor }">{{ rankPlusText }}</span><!--
                                        --><span v-if="rankTextAfter" :style="{ color: profileData.rank.color }">{{ rankTextAfter }}</span>
                                        </span>
                                        <span class="player-username" :style="{ color: profileData.rank.color }">{{ profileData.username }}</span>
                                    </template>
                                    <span v-else class="player-username" style="color: #AAAAAA">{{ profileData?.username }}</span>
                                </div>
                                <PlayerModel :uuid="profileData?.uuid" :width="144" :height="280" />
                            </div>

                            <div class="flex-1 min-w-0 space-y-8 lg:space-y-10">

                                <!-- ARMOR -->
                                <section v-if="currentData?.armor?.length">
                                    <h3 class="stat-header">{{ t('profileStats.armor') }}</h3>
                                    <div v-if="currentData.armor.some(a => a)" class="pieces">
                                        <ItemSlot v-for="(item, i) in [...currentData.armor].reverse()" :key="'a'+i" :item="item" />
                                    </div>
                                    <div v-if="Object.keys(armorStats).length" class="mt-3 text-xs font-semibold">
                                        <span class="text-neutral">{{ t('profileStats.bonus') }}</span>
                                        <template v-for="(stat, key, idx) in armorStats" :key="key">
                                            <span v-if="idx > 0" class="text-neutral opacity-50"> // </span>
                                            <span :style="{ color: getStatColor(key) }">{{ formatStat(key, stat) }}</span>
                                        </template>
                                    </div>
                                </section>

                                <!-- EQUIPMENT -->
                                <section v-if="currentData?.equipment?.length">
                                    <h3 class="stat-header">{{ t('profileStats.equipment') }}</h3>
                                    <div class="pieces">
                                        <ItemSlot v-for="(item, i) in [...currentData.equipment].reverse()" :key="'e'+i" :item="item" />
                                    </div>
                                    <div v-if="Object.keys(equipmentStats).length" class="mt-3 text-xs font-semibold">
                                        <span class="text-neutral">{{ t('profileStats.bonus') }}</span>
                                        <template v-for="(stat, key, idx) in equipmentStats" :key="key">
                                            <span v-if="idx > 0" class="text-neutral opacity-50"> // </span>
                                            <span :style="{ color: getStatColor(key) }">{{ formatStat(key, stat) }}</span>
                                        </template>
                                    </div>
                                </section>

                                <!-- WARDROBE -->
                                <section v-if="currentData?.wardrobe?.length">
                                    <h3 class="stat-header">{{ t('profileStats.wardrobe') }}</h3>
                                    <div class="wardrobe">
                                        <div v-for="(set, si) in currentData.wardrobe" :key="si"
                                             class="wardrobe-set"
                                             :class="{ 'ring-2 ring-profit/40 rounded': currentData.wardrobe_slot === si + 1 }">
                                            <template v-for="(item, ri) in set" :key="ri">
                                                <ItemSlot v-if="item" :item="item" />
                                                <div v-else class="armor-placeholder">
                                                    <div class="placeholder-icon"></div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </section>

                                <!-- WEAPONS -->
                                <section v-if="currentData?.weapons?.length">
                                    <h3 class="stat-header">{{ t('profileStats.weapons') }}</h3>
                                    <div v-if="activeWeapon" class="mb-3 text-sm font-semibold">
                                        <span class="text-neutral">{{ t('profileStats.activeWeapon') }}</span>
                                        <span :style="{ color: getRarityColor(activeWeapon.rarity) }">
                                            {{ activeWeapon.name }}
                                        </span>
                                    </div>
                                    <div class="pieces">
                                        <ItemSlot v-for="(item, i) in currentData.weapons" :key="'w'+i" :item="item" />
                                    </div>
                                </section>

                                <!-- No gear fallback -->
                                <div v-if="!currentData?.armor?.length && !currentData?.equipment?.length && !currentData?.weapons?.length"
                                     class="text-neutral text-sm py-8 text-center">
                                    {{ t('profileStats.noGearData') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  ACCESSORIES TAB                                    -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'accessories'">
                        <div v-if="currentData?.accessories?.length">
                            <div class="mb-6 space-y-1 text-sm font-semibold">
                                <div>
                                    <span class="text-neutral">{{ t('profileStats.uniqueAccessories') }}</span>
                                    <span class="text-white">{{ accessoryStats.unique }} / {{ accessoryStats.total }}</span>
                                </div>
                                <div>
                                    <span class="text-neutral">{{ t('profileStats.recombobulated') }}</span>
                                    <span class="text-white">{{ accessoryStats.recombobulated }} / {{ accessoryStats.total }}</span>
                                </div>
                                <div v-if="accessoryBag.selected_power">
                                    <span class="text-neutral">{{ t('profileStats.selectedPower') }}</span>
                                    <span class="text-profit">{{ capitalize(accessoryBag.selected_power) }}</span>
                                </div>
                                <div v-if="accessoryBag.highest_magical_power">
                                    <span class="text-neutral">{{ t('profileStats.magicalPower') }}</span>
                                    <span class="text-rarity-mythic">{{ accessoryBag.highest_magical_power }}</span>
                                </div>
                            </div>

                            <h3 class="stat-header">{{ t('profileStats.activeAccessories') }}</h3>
                            <div class="pieces">
                                <ItemSlot v-for="(item, i) in currentData.accessories" :key="i" :item="item" />
                            </div>
                        </div>
                        <div v-else class="text-neutral text-sm py-8 text-center">
                            {{ t('profileStats.noAccessoryData') }}
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  INVENTORY TAB (SkyBlock UI style with sub-tabs)      -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'inventory'">
                        <div class="inventory-container">
                            <!-- Inventory sub-tab headers -->
                            <div class="inv-tabs">
                                <button v-for="subTab in inventorySubTabs" :key="subTab.id"
                                    @click="activeInventorySubTab = subTab.id; expandedBackpack = null; expandedEnderPage = null; expandedRiftEnderPage = null"
                                    class="inv-tab"
                                    :class="{ 'active-tab': activeInventorySubTab === subTab.id }">
                                    <img v-if="subTab.icon" :src="subTab.icon" class="inv-tab-icon" loading="lazy" />
                                    <span>{{ subTab.name }}</span>
                                </button>
                            </div>

                            <!-- BACKPACK / STORAGE sub-tab -->
                            <div v-if="activeInventorySubTab === 'backpack'">
                                <div v-if="backpackStorage.length > 0">
                                    <!-- Backpack cards (always visible) -->
                                    <div class="storage-cards">
                                        <button v-for="(bp, idx) in backpackStorage" :key="idx"
                                                class="storage-card"
                                                :class="{ 'storage-card-active': expandedBackpack === idx }"
                                                @click="expandedBackpack = expandedBackpack === idx ? null : idx">
                                            <img v-if="getItemTextureUrl(bp.icon)"
                                                 :src="getItemTextureUrl(bp.icon)"
                                                 class="storage-card-img" loading="lazy" draggable="false" />
                                            <div class="storage-card-info">
                                                <span class="storage-card-name">{{ bp.icon?.name || `${t('profileStats.backpack')} ${bp.slot + 1}` }}</span>
                                                <span class="storage-card-count">{{ bp.count }} {{ t('profileStats.items') }}</span>
                                            </div>
                                            <span class="storage-card-toggle">{{ expandedBackpack === idx ? '▲' : '▼' }}</span>
                                        </button>
                                    </div>

                                    <!-- Expanded backpack contents (below cards) -->
                                    <div v-if="expandedBackpack !== null" class="storage-expanded">
                                        <InventoryGrid :items="backpackStorage[expandedBackpack]?.items ?? []" />
                                    </div>
                                </div>
                                <div v-else class="text-neutral text-sm py-8 text-center">
                                    {{ t('profileStats.noBackpackData') }}
                                </div>
                            </div>

                            <!-- ENDERCHEST sub-tab (pages) -->
                            <div v-else-if="activeInventorySubTab === 'enderchest'">
                                <div v-if="enderchestPages.length > 0">
                                    <!-- Enderchest page cards (always visible) -->
                                    <div class="storage-cards">
                                        <button v-for="(page, idx) in enderchestPages" :key="idx"
                                                class="storage-card"
                                                :class="{ 'storage-card-active': expandedEnderPage === idx }"
                                                @click="expandedEnderPage = expandedEnderPage === idx ? null : idx">
                                            <img src="/img/textures/ender_chest.png"
                                                 class="storage-card-img" loading="lazy" draggable="false" />
                                            <div class="storage-card-info">
                                                <span class="storage-card-name">{{ t('profileStats.page') }} {{ idx + 1 }}</span>
                                                <span class="storage-card-count">{{ page.count }} {{ t('profileStats.items') }}</span>
                                            </div>
                                            <span class="storage-card-toggle">{{ expandedEnderPage === idx ? '▲' : '▼' }}</span>
                                        </button>
                                    </div>

                                    <!-- Expanded enderchest page contents (below cards) -->
                                    <div v-if="expandedEnderPage !== null" class="storage-expanded">
                                        <InventoryGrid :items="enderchestPages[expandedEnderPage]?.items ?? []" />
                                    </div>
                                </div>
                                <div v-else class="text-neutral text-sm py-8 text-center">
                                    {{ t('profileStats.noEnderChestData') }}
                                </div>
                            </div>

                            <!-- RIFT ENDERCHEST sub-tab (pages) -->
                            <div v-else-if="activeInventorySubTab === 'rift_enderchest'">
                                <div v-if="riftEnderchestPages.length > 0">
                                    <!-- Rift enderchest page cards (always visible) -->
                                    <div class="storage-cards">
                                        <button v-for="(page, idx) in riftEnderchestPages" :key="idx"
                                                class="storage-card"
                                                :class="{ 'storage-card-active': expandedRiftEnderPage === idx }"
                                                @click="expandedRiftEnderPage = expandedRiftEnderPage === idx ? null : idx">
                                            <img src="/img/textures/ender_chest.png"
                                                 class="storage-card-img" loading="lazy" draggable="false" />
                                            <div class="storage-card-info">
                                                <span class="storage-card-name">{{ t('profileStats.page') }} {{ idx + 1 }}</span>
                                                <span class="storage-card-count">{{ page.count }} {{ t('profileStats.items') }}</span>
                                            </div>
                                            <span class="storage-card-toggle">{{ expandedRiftEnderPage === idx ? '▲' : '▼' }}</span>
                                        </button>
                                    </div>

                                    <!-- Expanded rift enderchest page contents -->
                                    <div v-if="expandedRiftEnderPage !== null" class="storage-expanded">
                                        <InventoryGrid :items="riftEnderchestPages[expandedRiftEnderPage]?.items ?? []" />
                                    </div>
                                </div>
                                <div v-else class="text-neutral text-sm py-8 text-center">
                                    {{ t('profileStats.noRiftEnderChestData') }}
                                </div>
                            </div>

                            <!-- MUSEUM sub-tab -->
                            <div v-else-if="activeInventorySubTab === 'museum'">
                                <div v-if="museumData.items?.length > 0 || museumData.special?.length > 0">
                                    <!-- Museum summary -->
                                    <div class="mb-4 space-y-1 text-sm">
                                        <div>
                                            <span class="text-neutral">{{ t('profileStats.museumValue') }}</span>
                                            <span class="text-legendary font-bold">{{ fNum(museumData.value) }} {{ t('profileStats.coins') }}</span>
                                        </div>
                                        <div>
                                            <span class="text-neutral">{{ t('profileStats.appraisal') }}</span>
                                            <span :class="museumData.appraisal ? 'text-profit' : 'text-loss'">
                                                {{ museumData.appraisal ? t('profileStats.unlocked') : t('profileStats.locked') }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-neutral">{{ t('profileStats.itemsDonated') }}</span>
                                            <span class="text-white font-bold">{{ museumData.items?.length ?? 0 }}</span>
                                        </div>
                                        <div v-if="museumData.special?.length > 0">
                                            <span class="text-neutral">{{ t('profileStats.specialItems') }}</span>
                                            <span class="text-white font-bold">{{ museumData.special.length }}</span>
                                        </div>
                                    </div>

                                    <!-- Museum items grid -->
                                    <div v-if="museumData.items?.length > 0">
                                        <h4 class="text-white text-sm font-bold mb-2">{{ t('profileStats.donatedItems') }}</h4>
                                        <div class="inventory-grid">
                                            <template v-for="(mItem, idx) in museumData.items" :key="'museum-'+idx">
                                                <template v-for="(item, jdx) in (mItem.data ?? [])" :key="'mi-'+idx+'-'+jdx">
                                                    <ItemSlot :item="item" />
                                                </template>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Special items grid -->
                                    <div v-if="museumData.special?.length > 0" class="mt-4">
                                        <h4 class="text-white text-sm font-bold mb-2">{{ t('profileStats.specialItemsTitle') }}</h4>
                                        <div class="inventory-grid">
                                            <template v-for="(mItem, idx) in museumData.special" :key="'special-'+idx">
                                                <template v-for="(item, jdx) in (mItem.data ?? [])" :key="'si-'+idx+'-'+jdx">
                                                    <ItemSlot :item="item" />
                                                </template>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-neutral text-sm py-8 text-center">
                                    {{ t('profileStats.noMuseumData') }}
                                </div>
                            </div>

                            <!-- Regular inventory sub-tabs (9-column grid) -->
                            <div v-else>
                                <div v-if="inventorySubTabData.length > 0">
                                    <InventoryGrid
                                        :items="inventorySubTabData"
                                        :show-hotbar="activeInventorySubTab === 'inv'" />
                                </div>
                                <div v-else class="text-neutral text-sm py-8 text-center">
                                    <template v-if="currentData?.inv_disabled">
                                        {{ t('profileStats.inventoryApiDisabled') }}
                                    </template>
                                    <template v-else>
                                        {{ t('profileStats.noInventoryData') }}
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  PETS TAB                                          -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'pets'">
                        <template v-if="currentData?.pets?.pets?.length > 0">
                            <!-- ── Stats header ─────────────────────────── -->
                            <div class="pets-stats-header">
                                <div class="pets-stat">
                                    <span class="pets-stat-label">{{ t('profileStats.uniquePets') }}</span>
                                    <span class="pets-stat-value">{{ currentData.pets.amount }} / {{ currentData.pets.total }}</span>
                                </div>
                                <div class="pets-stat">
                                    <span class="pets-stat-label">{{ t('profileStats.uniquePetSkins') }}</span>
                                    <span class="pets-stat-value">{{ currentData.pets.amountSkins }}</span>
                                </div>
                                <div class="pets-stat">
                                    <span class="pets-stat-label">{{ t('profileStats.petScore') }}</span>
                                    <span class="pets-stat-value">{{ currentData.pets.petScore?.total ?? 0 }} <span class="pets-stat-mf">(+{{ currentData.pets.petScore?.magicFind ?? 0 }} ✯ {{ t('profileStats.magicFind') }})</span></span>
                                </div>
                                <div class="pets-stat">
                                    <span class="pets-stat-label">{{ t('profileStats.totalCandiesUsed') }}</span>
                                    <span class="pets-stat-value">{{ (currentData.pets.totalCandy ?? 0).toLocaleString() }}</span>
                                </div>
                                <div class="pets-stat">
                                    <span class="pets-stat-label">{{ t('profileStats.totalPetXP') }}</span>
                                    <span class="pets-stat-value">{{ fNum(currentData.pets.totalPetXp ?? 0) }}</span>
                                </div>
                            </div>

                            <!-- ── Active Pet ───────────────────────────── -->
                            <template v-if="activePet">
                                <h3 class="pets-section-title">{{ t('profileStats.activePet') }}</h3>
                                <div class="pets-active-section">
                                    <div class="pets-active-item">
                                        <ItemSlot :item="activePet" />
                                        <div class="pets-active-info">
                                            <span class="pets-active-name" :style="{ color: petTierColors[activePet.tier] || '#AAAAAA' }">
                                                {{ activePet.name }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- ── Other Pets (unique, not active) ──────── -->
                            <h3 class="pets-section-title">{{ t('profileStats.pets') }}</h3>
                            <div class="pets-grid">
                                <div v-for="(pet, i) in displayUniquePets" :key="'u-'+i" class="pets-grid-item">
                                    <ItemSlot :item="pet" />
                                    <span class="pets-level-label" :style="{ color: petTierColors[pet.tier] || '#AAAAAA' }">
                                        {{ t('profileStats.level') }} {{ pet.level?.level ?? '?' }}
                                    </span>
                                </div>
                            </div>

                            <!-- ── Show More Pets (duplicates) ──────────── -->
                            <template v-if="currentData.pets.otherPets?.length > 0">
                                <div class="pets-collapsible">
                                    <button class="pets-collapsible-btn" @click="showMorePets = !showMorePets">
                                        {{ showMorePets ? '▼' : '▶' }} {{ t('profileStats.showMorePets') }} ({{ currentData.pets.otherPets.length }})
                                    </button>
                                    <div v-if="showMorePets" class="pets-grid" style="margin-top: 8px;">
                                        <div v-for="(pet, i) in currentData.pets.otherPets" :key="'o-'+i" class="pets-grid-item">
                                            <ItemSlot :item="pet" />
                                            <span class="pets-level-label" :style="{ color: petTierColors[pet.tier] || '#AAAAAA' }">
                                                {{ t('profileStats.level') }} {{ pet.level?.level ?? '?' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- ── Missing Pets ─────────────────────────── -->
                            <template v-if="currentData.pets.missing?.length > 0">
                                <div class="pets-collapsible">
                                    <button class="pets-collapsible-btn" @click="showMissingPets = !showMissingPets">
                                        {{ showMissingPets ? '▼' : '▶' }} {{ t('profileStats.missingPets') }} ({{ currentData.pets.missing.length }})
                                    </button>
                                    <div v-if="showMissingPets" class="pets-grid pets-missing-grid" style="margin-top: 8px;">
                                        <div v-for="(pet, i) in currentData.pets.missing" :key="'m-'+i" class="pets-grid-item pets-missing-item">
                                            <ItemSlot :item="pet" />
                                            <span class="pets-level-label" style="color: #555;">
                                                {{ pet.name }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </template>
                        <div v-else class="text-neutral text-sm py-8 text-center">{{ t('profileStats.noPetData') }}</div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  SKILLS TAB                                        -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'skills'">
                        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
                            <div class="hidden lg:block w-52 shrink-0">
                                <div class="sticky top-20">
                                    <div class="player-name-rank">
                                        <template v-if="profileData?.rank?.prefix">
                                            <span class="rank-prefix" :style="{ color: profileData.rank.color }">
                                                {{ rankTextBefore }}<!--
                                            --><span v-if="rankPlusText" class="rank-plus" :style="{ color: profileData.rank.plusColor }">{{ rankPlusText }}</span><!--
                                            --><span v-if="rankTextAfter" :style="{ color: profileData.rank.color }">{{ rankTextAfter }}</span>
                                            </span>
                                            <span class="player-username" :style="{ color: profileData.rank.color }">{{ profileData.username }}</span>
                                        </template>
                                        <span v-else class="player-username" style="color: #AAAAAA">{{ profileData?.username }}</span>
                                    </div>
                                    <PlayerModel :uuid="profileData?.uuid" :width="208" :height="400" />
                                </div>
                            </div>

                            <div class="flex flex-col items-center lg:hidden">
                                <div class="player-name-rank">
                                    <template v-if="profileData?.rank?.prefix">
                                        <span class="rank-prefix" :style="{ color: profileData.rank.color }">
                                            {{ rankTextBefore }}<!--
                                        --><span v-if="rankPlusText" class="rank-plus" :style="{ color: profileData.rank.plusColor }">{{ rankPlusText }}</span><!--
                                        --><span v-if="rankTextAfter" :style="{ color: profileData.rank.color }">{{ rankTextAfter }}</span>
                                        </span>
                                        <span class="player-username" :style="{ color: profileData.rank.color }">{{ profileData.username }}</span>
                                    </template>
                                    <span v-else class="player-username" style="color: #AAAAAA">{{ profileData?.username }}</span>
                                </div>
                                <PlayerModel :uuid="profileData?.uuid" :width="144" :height="280" />
                            </div>

                            <div class="flex-1 min-w-0 ps-skills-panel">
                                <!-- SkyBlock Level (full width) -->
                                <div v-if="currentData?.skyblock_level" class="ps-skill-block ps-skill-block--level">
                                    <div class="ps-skill-icon-ring ps-skill-icon-ring--amber">
                                        <img
                                            v-if="getItemTextureUrl(SB_LEVEL_TEXTURE_ITEM)"
                                            :src="getItemTextureUrl(SB_LEVEL_TEXTURE_ITEM)"
                                            class="ps-skill-icon-img"
                                            alt=""
                                            loading="lazy"
                                            draggable="false"
                                        />
                                        <span v-else class="ps-skill-icon-emoji" aria-hidden="true">✫</span>
                                    </div>
                                    <div class="ps-skill-head ps-skill-head--level">
                                        {{ t('profileStats.level') }}
                                        <span class="ps-skill-head-lvl">{{ currentData.skyblock_level.level }}</span>
                                    </div>
                                    <div class="ps-skill-pill">
                                        <div
                                            class="ps-skill-pill-fill ps-skill-pill-fill--gold"
                                            :style="{ width: (currentData.skyblock_level.progress * 100) + '%' }"
                                        />
                                        <span class="ps-skill-pill-xp">{{ formatSkyblockLevelXP(currentData.skyblock_level) }}</span>
                                    </div>
                                </div>

                                <div class="skills-grid ps-skills-grid">
                                    <div v-for="skill in leftSkills" :key="skill.name" class="ps-skill-block">
                                        <div
                                            class="ps-skill-icon-ring"
                                            :class="{
                                                'ps-skill-icon-ring--max': skill.level >= skill.maxLevel,
                                            }"
                                        >
                                            <img
                                                v-if="skillTextureUrl(skill.name)"
                                                :src="skillTextureUrl(skill.name)"
                                                class="ps-skill-icon-img"
                                                alt=""
                                                loading="lazy"
                                                draggable="false"
                                            />
                                            <span v-else class="ps-skill-icon-emoji" aria-hidden="true">{{ SKILL_ICONS[skill.name] || '❓' }}</span>
                                        </div>
                                        <div class="ps-skill-head">
                                            {{ capitalize(skill.name) }}
                                            <span class="ps-skill-head-lvl">{{ skill.level >= skill.maxLevel ? skill.maxLevel : skill.level }}</span>
                                        </div>
                                        <div class="ps-skill-pill">
                                            <div
                                                class="ps-skill-pill-fill"
                                                :class="skillPillFillClass(skill)"
                                                :style="{ width: (skill.level >= skill.maxLevel ? 100 : skill.progress * 100) + '%' }"
                                            />
                                            <span class="ps-skill-pill-xp">{{ formatXP(skill) }}</span>
                                        </div>
                                    </div>
                                    <div v-for="skill in rightSkills" :key="skill.name" class="ps-skill-block">
                                        <div
                                            class="ps-skill-icon-ring"
                                            :class="{
                                                'ps-skill-icon-ring--max': skill.level >= skill.maxLevel,
                                            }"
                                        >
                                            <img
                                                v-if="skillTextureUrl(skill.name)"
                                                :src="skillTextureUrl(skill.name)"
                                                class="ps-skill-icon-img"
                                                alt=""
                                                loading="lazy"
                                                draggable="false"
                                            />
                                            <span v-else class="ps-skill-icon-emoji" aria-hidden="true">{{ SKILL_ICONS[skill.name] || '❓' }}</span>
                                        </div>
                                        <div class="ps-skill-head">
                                            {{ capitalize(skill.name) }}
                                            <span class="ps-skill-head-lvl">{{ skill.level >= skill.maxLevel ? skill.maxLevel : skill.level }}</span>
                                        </div>
                                        <div class="ps-skill-pill">
                                            <div
                                                class="ps-skill-pill-fill"
                                                :class="skillPillFillClass(skill)"
                                                :style="{ width: (skill.level >= skill.maxLevel ? 100 : skill.progress * 100) + '%' }"
                                            />
                                            <span class="ps-skill-pill-xp">{{ formatXP(skill) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Player Stats -->
                                <div v-if="currentData?.player_stats?.length" class="stats-section">
                                    <div class="stats-grid ps-stats-grid">
                                        <span v-for="stat in currentData.player_stats" :key="stat.name"
                                              class="stat-chip"
                                              :style="{ '--stat-color': stat.color }">
                                            <span class="stat-icon">{{ stat.icon }}</span>
                                            <span class="stat-name">{{ stat.name }}</span>
                                            <span class="stat-value">{{ stat.value.toLocaleString() }}{{ stat.suffix }}</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  SLAYER TAB  (SkyBlock UI style)                      -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'slayer'">
                        <div v-if="slayerData && Object.keys(slayerData.slayers).length > 0">
                            <!-- Total Slayer XP -->
                            <div class="mb-4 text-sm text-neutral">
                                {{ t('profileStats.totalSlayerXP') }} <b class="text-white">{{ Number(slayerData.total_slayer_xp).toLocaleString() }}</b>
                            </div>

                            <div class="grid grid-cols-1 gap-3 min-w-0 md:grid-cols-2 md:gap-4 lg:grid-cols-3">
                                <div v-for="(slayer, key) in slayerData.slayers" :key="key"
                                     class="slayer-card">
                                    <!-- Header: icon + boss name -->
                                    <div class="slayer-header">
                                        <span class="text-lg">{{ SLAYER_ICONS[key] || '💀' }}</span>
                                        <span class="slayer-boss-name">{{ slayer.name }}</span>
                                    </div>

                                    <!-- Tier kills table -->
                                    <div class="slayer-tiers">
                                        <div v-for="tier in slayerMaxTier(key)" :key="tier" class="slayer-tier-col">
                                            <span class="slayer-tier-label">{{ t('profileStats.tier') }} {{ romanNumeral(tier) }}</span>
                                            <span class="slayer-tier-value">{{ (slayer.kills?.[tier] ?? 0).toLocaleString() }}</span>
                                        </div>
                                        <div class="slayer-tier-col">
                                            <span class="slayer-tier-label">{{ t('profileStats.total') }}</span>
                                            <span class="slayer-tier-value">{{ (slayer.total_kills ?? 0).toLocaleString() }}</span>
                                        </div>
                                    </div>

                                    <!-- Level label -->
                                    <div class="slayer-level-label">
                                        {{ capitalize(key) }} {{ t('profileStats.level') }} {{ slayer.level?.currentLevel ?? 0 }}
                                    </div>

                                    <!-- XP progress bar -->
                                    <div class="slayer-xp-bar-track">
                                        <div class="slayer-xp-bar-fill"
                                             :class="slayer.level?.currentLevel >= slayer.level?.maxLevel ? 'bar-maxed' : ''"
                                             :style="{ width: ((slayer.level?.progress ?? 0) * 100) + '%' }"></div>
                                        <span class="slayer-xp-bar-text">{{ formatSlayerXP(slayer) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-neutral text-sm py-8 text-center">{{ t('profileStats.noSlayerData') }}</div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  DUNGEONS TAB                                      -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'dungeons'">
                        <div v-if="currentData?.dungeons && Object.keys(currentData.dungeons).length > 0">
                            <!-- ── Catacombs + Classes skill bars (SkyBlock UI style) ── -->
                            <div class="dungeon-skill-bars">
                                <!-- Catacombs main level -->
                                <div class="skill-row">
                                    <div class="skill-label">
                                        <span class="skill-icon">💀</span>
                                        <span class="skill-name">{{ t('profileStats.catacombs') }}</span>
                                        <span class="skill-level" :class="dungeonLevelClass(currentData.dungeons.catacombs?.level)">
                                            {{ currentData.dungeons.catacombs?.level?.level ?? 0 }}
                                        </span>
                                    </div>
                                    <div class="skill-bar-track">
                                        <div class="skill-bar-fill"
                                             :class="dungeonBarClass(currentData.dungeons.catacombs?.level)"
                                             :style="{ width: ((currentData.dungeons.catacombs?.level?.progress ?? 0) * 100) + '%' }"></div>
                                        <span class="skill-bar-text">{{ formatDungeonXP(currentData.dungeons.catacombs?.level) }}</span>
                                    </div>
                                </div>

                                <!-- Class skill bars -->
                                <template v-for="(cls, name) in currentData.dungeons.classes" :key="name">
                                    <div class="skill-row">
                                        <div class="skill-label">
                                            <span class="skill-icon">{{ CLASS_ICONS[name] || '🎮' }}</span>
                                            <span class="skill-name">{{ capitalize(name) }}</span>
                                            <span class="skill-level" :class="dungeonClassLevelClass(cls, name)">
                                                {{ cls.level }}
                                            </span>
                                        </div>
                                        <div class="skill-bar-track">
                                            <div class="skill-bar-fill"
                                                 :class="dungeonClassBarClass(cls)"
                                                 :style="{ width: (cls.level >= 50 ? 100 : cls.progress * 100) + '%' }"></div>
                                            <span class="skill-bar-text">{{ formatDungeonClassXP(cls) }}</span>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- ── Dungeon summary info ── -->
                            <div class="dungeon-summary">
                                <div class="dungeon-summary-row">
                                    <span class="dungeon-stat-label">{{ t('profileStats.selectedClass') }}</span>
                                    <span class="dungeon-stat-value">{{ capitalize(currentData.dungeons.selected_class || t('profileStats.none')) }}</span>
                                </div>
                                <div class="dungeon-summary-row">
                                    <span class="dungeon-stat-label" :class="{ 'text-gold': allClassesMaxed }">{{ t('profileStats.classAverage') }}</span>
                                    <span class="dungeon-stat-value" :class="{ 'text-gold': allClassesMaxed }">{{ currentData.dungeons.class_average?.toFixed(2) ?? '0.00' }}</span>
                                </div>
                                <div class="dungeon-summary-row">
                                    <span class="dungeon-stat-label" :class="{ 'text-gold': currentData.dungeons.highest_floor === 7 }">{{ t('profileStats.highestFloorNormal') }}</span>
                                    <span class="dungeon-stat-value" :class="{ 'text-gold': currentData.dungeons.highest_floor === 7 }">{{ currentData.dungeons.highest_floor !== null ? currentData.dungeons.highest_floor : '—' }}</span>
                                </div>
                                <div v-if="currentData.dungeons.highest_master !== null" class="dungeon-summary-row">
                                    <span class="dungeon-stat-label" :class="{ 'text-gold': currentData.dungeons.highest_master === 7 }">{{ t('profileStats.highestFloorMaster') }}</span>
                                    <span class="dungeon-stat-value" :class="{ 'text-gold': currentData.dungeons.highest_master === 7 }">{{ currentData.dungeons.highest_master }}</span>
                                </div>
                                <div class="dungeon-summary-row">
                                    <span class="dungeon-stat-label">{{ t('profileStats.secretsFound') }}</span>
                                    <span class="dungeon-stat-value">{{ (currentData.dungeons.secrets_found ?? 0).toLocaleString() }}</span>
                                    <span class="dungeon-stat-note">({{ currentData.dungeons.secrets_per_run ?? 0 }} {{ t('profileStats.secretsPerRun') }})</span>
                                </div>
                            </div>

                            <!-- ── Normal Catacombs floors ── -->
                            <div v-if="currentData.dungeons.floors?.length" class="dungeon-floors-section">
                                <h3 class="dungeon-section-title">{{ t('profileStats.catacombs') }}</h3>
                                <div class="dungeon-floor-grid">
                                    <div v-for="floor in currentData.dungeons.floors" :key="'f'+floor.index" class="dungeon-floor-card">
                                        <div class="dungeon-floor-header">
                                            <span class="dungeon-floor-name">{{ floor.name.toUpperCase() }}</span>
                                        </div>
                                        <div class="dungeon-floor-body">
                                            <!-- Floor Stats -->
                                            <details v-if="Object.keys(floor.stats).length">
                                                <summary class="dungeon-details-toggle">{{ t('profileStats.floorStats') }}</summary>
                                                <div class="dungeon-details-content">
                                                    <div v-for="(val, key) in floor.stats" :key="key" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ formatStatName(key) }}:</span>
                                                        <span class="dungeon-detail-value">{{ formatFloorStat(key, val) }}</span>
                                                    </div>
                                                    <div v-if="floor.most_damage" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.mostDamage') }}</span>
                                                        <span class="dungeon-detail-value">{{ fNum(floor.most_damage.value) }} <span class="dungeon-stat-note">({{ capitalize(floor.most_damage.class) }})</span></span>
                                                    </div>
                                                </div>
                                            </details>
                                            <!-- Best Run -->
                                            <details v-if="floor.best_run">
                                                <summary class="dungeon-details-toggle">{{ t('profileStats.bestRun') }}</summary>
                                                <div class="dungeon-details-content">
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.grade') }}</span>
                                                        <span class="dungeon-detail-value dungeon-grade" :class="'grade-' + floor.best_run.grade?.replace('+','plus')">{{ floor.best_run.grade }}</span>
                                                    </div>
                                                    <div v-if="floor.best_run.timestamp" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.timestamp') }}</span>
                                                        <span class="dungeon-detail-value">{{ timeAgo(floor.best_run.timestamp) }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.scoreExploration') }}</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.score_exploration }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.scoreSpeed') }}</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.score_speed }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.scoreSkill') }}</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.score_skill }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.scoreBonus') }}</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.score_bonus }}</span>
                                                    </div>
                                                    <div v-if="floor.best_run.dungeon_class" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.dungeonClass') }}</span>
                                                        <span class="dungeon-detail-value">{{ capitalize(floor.best_run.dungeon_class) }}</span>
                                                    </div>
                                                    <div v-if="floor.best_run.elapsed_time" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.elapsedTime') }}</span>
                                                        <span class="dungeon-detail-value">{{ formatElapsed(floor.best_run.elapsed_time) }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.damageDealt') }}</span>
                                                        <span class="dungeon-detail-value">{{ fNum(floor.best_run.damage_dealt) }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.deaths') }}</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.deaths }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.mobsKilled') }}</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.mobs_killed }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.secretsFound') }}</span>
                                                        <span class="dungeon-detail-value">{{ floor.best_run.secrets_found }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.damageMitigated') }}</span>
                                                        <span class="dungeon-detail-value">{{ fNum(floor.best_run.damage_mitigated) }}</span>
                                                    </div>
                                                </div>
                                            </details>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Master Catacombs floors ── -->
                            <div v-if="currentData.dungeons.master_floors?.length" class="dungeon-floors-section">
                                <h3 class="dungeon-section-title">{{ t('profileStats.masterCatacombs') }}</h3>
                                <div class="dungeon-floor-grid">
                                    <div v-for="floor in currentData.dungeons.master_floors" :key="'m'+floor.index" class="dungeon-floor-card dungeon-floor-master">
                                        <div class="dungeon-floor-header">
                                            <span class="dungeon-floor-name">{{ floor.name.toUpperCase() }}</span>
                                        </div>
                                        <div class="dungeon-floor-body">
                                            <details v-if="Object.keys(floor.stats).length">
                                                <summary class="dungeon-details-toggle">{{ t('profileStats.floorStats') }}</summary>
                                                <div class="dungeon-details-content">
                                                    <div v-for="(val, key) in floor.stats" :key="key" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ formatStatName(key) }}:</span>
                                                        <span class="dungeon-detail-value">{{ formatFloorStat(key, val) }}</span>
                                                    </div>
                                                    <div v-if="floor.most_damage" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.mostDamage') }}</span>
                                                        <span class="dungeon-detail-value">{{ fNum(floor.most_damage.value) }} <span class="dungeon-stat-note">({{ capitalize(floor.most_damage.class) }})</span></span>
                                                    </div>
                                                </div>
                                            </details>
                                            <details v-if="floor.best_run">
                                                <summary class="dungeon-details-toggle">{{ t('profileStats.bestRun') }}</summary>
                                                <div class="dungeon-details-content">
                                                    <div class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.grade') }}</span>
                                                        <span class="dungeon-detail-value dungeon-grade" :class="'grade-' + floor.best_run.grade?.replace('+','plus')">{{ floor.best_run.grade }}</span>
                                                    </div>
                                                    <div v-if="floor.best_run.timestamp" class="dungeon-detail-row">
                                                        <span class="dungeon-detail-label">{{ t('profileStats.timestamp') }}</span>
                                                        <span class="dungeon-detail-value">{{ timeAgo(floor.best_run.timestamp) }}</span>
                                                    </div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.scoreExploration') }}</span><span class="dungeon-detail-value">{{ floor.best_run.score_exploration }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.scoreSpeed') }}</span><span class="dungeon-detail-value">{{ floor.best_run.score_speed }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.scoreSkill') }}</span><span class="dungeon-detail-value">{{ floor.best_run.score_skill }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.scoreBonus') }}</span><span class="dungeon-detail-value">{{ floor.best_run.score_bonus }}</span></div>
                                                    <div v-if="floor.best_run.dungeon_class" class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.dungeonClass') }}</span><span class="dungeon-detail-value">{{ capitalize(floor.best_run.dungeon_class) }}</span></div>
                                                    <div v-if="floor.best_run.elapsed_time" class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.elapsedTime') }}</span><span class="dungeon-detail-value">{{ formatElapsed(floor.best_run.elapsed_time) }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.damageDealt') }}</span><span class="dungeon-detail-value">{{ fNum(floor.best_run.damage_dealt) }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.deaths') }}</span><span class="dungeon-detail-value">{{ floor.best_run.deaths }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.mobsKilled') }}</span><span class="dungeon-detail-value">{{ floor.best_run.mobs_killed }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.secretsFound') }}</span><span class="dungeon-detail-value">{{ floor.best_run.secrets_found }}</span></div>
                                                    <div class="dungeon-detail-row"><span class="dungeon-detail-label">{{ t('profileStats.damageMitigated') }}</span><span class="dungeon-detail-value">{{ fNum(floor.best_run.damage_mitigated) }}</span></div>
                                                </div>
                                            </details>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-neutral text-sm py-8 text-center">{{ t('profileStats.noDungeonData') }}</div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!--  COLLECTIONS TAB                                   -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div v-if="activeTab === 'collections'">
                        <div v-if="collectionsData && Object.keys(collectionsData.categories).length > 0">
                            <!-- Maxed summary -->
                            <div class="mb-4 text-sm text-neutral">
                                {{ t('profileStats.maxedCollections') }} <b class="text-white">{{ collectionsData.maxedCollections }}</b> / {{ collectionsData.totalCollections }}
                            </div>

                            <!-- Category sections -->
                            <div class="space-y-5">
                                <div v-for="(cat, catId) in collectionsData.categories" :key="catId" class="collection-category">
                                    <!-- Category header -->
                                    <div class="collection-category-header">
                                        <span class="text-base">{{ COLLECTION_CATEGORY_ICONS[catId] || '📦' }}</span>
                                        <span class="collection-category-name">{{ cat.name }}</span>
                                        <span v-if="cat.maxedTiers >= cat.totalTiers" class="collection-max-badge">{{ t('profileStats.maxLabel') }}</span>
                                        <span v-else class="collection-category-count">({{ cat.maxedTiers }} / {{ cat.totalTiers }} {{ t('profileStats.maxShort') }})</span>
                                    </div>

                                    <!-- Collection items flow -->
                                    <div class="collection-items-grid">
                                        <div v-for="item in cat.collections" :key="item.id"
                                             class="collection-row-card"
                                             :class="{ 'collection-row-card--locked': !item.unlocked || item.amount === 0 }">
                                            <div class="collection-row-icon-wrap"
                                                 :class="{ 'collection-row-icon-wrap--muted': !item.unlocked || item.amount === 0 }">
                                                <img v-if="getItemTextureUrl(collectionTextureItem(item))"
                                                     :src="getItemTextureUrl(collectionTextureItem(item))"
                                                     class="collection-row-icon"
                                                     alt=""
                                                     loading="lazy"
                                                     draggable="false" />
                                                <div v-else class="collection-row-icon collection-row-icon--placeholder" aria-hidden="true" />
                                            </div>
                                            <div class="collection-row-text">
                                                <div class="collection-row-titleline">
                                                    <span class="collection-row-name"
                                                          :class="(item.unlocked && item.amount > 0) ? 'collection-row-name--active' : 'collection-row-name--muted'">
                                                        {{ item.name }}
                                                    </span>
                                                    <span class="collection-row-tiernum"
                                                          :class="(item.unlocked && item.amount > 0) ? 'collection-row-tiernum--active' : 'collection-row-tiernum--muted'">
                                                        {{ item.tier }}
                                                    </span>
                                                </div>
                                                <div class="collection-row-amountline">
                                                    <span class="collection-row-amount-label">{{ t('profileStats.collectionAmount') }}</span>
                                                    <span class="collection-row-amount-value"
                                                          :class="{ 'collection-row-amount-value--muted': !item.unlocked || item.amount === 0 }">
                                                        {{ Number(item.amount ?? 0).toLocaleString() }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-neutral text-sm py-8 text-center">{{ t('profileStats.noCollectionData') }}</div>
                    </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
