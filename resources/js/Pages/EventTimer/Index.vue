<script setup>
import { computed, onBeforeUnmount, onMounted, provide, reactive, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { preloadAllTextures, setEnabledPacks } from '@/utils/textures';
import ItemSlot from '@/Components/SkyBlock/ItemSlot.vue';
import PackSelector from '@/Components/SkyBlock/PackSelector.vue';

const nowMs = ref(Date.now());
let timerId = null;
let notifyTickId = null;

const localTz = ref(Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC');
const notificationPermission = ref(typeof Notification !== 'undefined' ? Notification.permission : 'unsupported');
const swRegistration = ref(null);
const supportsTimestampTrigger = ref(typeof window !== 'undefined' && 'TimestampTrigger' in window);
const notifyEnabled = reactive({});
const sentKeys = ref(new Set());

const NOTIFY_LEAD_SECONDS = 5 * 60;
const NOTIFY_PREFS_KEY = 'sbh:event-timer:notify-enabled';
const NOTIFY_SENT_KEY = 'sbh:event-timer:notify-sent';

const textureVersion = ref(0);
provide('textureVersion', textureVersion);

const SKYBLOCK_DAYS_PER_MONTH = 31;
const SKYBLOCK_DAY_LENGTH_SECONDS = 20 * 60;
const SKYBLOCK_SECONDS_PER_DAY = 24 * 60 * 60;
const SKYBLOCK_DAYS_PER_YEAR = SKYBLOCK_DAYS_PER_MONTH * 12;
const SKYBLOCK_MONTHS_PER_YEAR = 12;
const SKYBLOCK_EPOCH_MS = new Date('Jun 11 2019 17:55:00 GMT').getTime();
const SKYBLOCK_CALENDAR_EVENT_DAY_OFFSET = -1;
const SKYBLOCK_MONTH_NAMES = [
    'Early Spring',
    'Spring',
    'Late Spring',
    'Early Summer',
    'Summer',
    'Late Summer',
    'Early Autumn',
    'Autumn',
    'Late Autumn',
    'Early Winter',
    'Winter',
    'Late Winter',
];

const SKYBLOCK_EVENT_RULES = [
    { key: 'spookyFestival', name: 'Spooky Festival', when: [{ start: { month: 9, day: 29 }, end: { month: 9, day: 31 } }] },
    { key: 'zoo', name: 'Travelling Zoo', when: [{ start: { month: 4, day: 1 }, end: { month: 4, day: 3 } }, { start: { month: 10, day: 1 }, end: { month: 10, day: 3 } }] },
    { key: 'jerryWorkshop', name: "Jerry's Workshop", when: [{ start: { month: 12, day: 1 }, end: { month: 12, day: 31 } }] },
    { key: 'winter', name: 'Season of Jerry', when: [{ start: { month: 12, day: 24 }, end: { month: 12, day: 26 } }] },
    { key: 'newYear', name: 'New Year Celebration', when: [{ start: { month: 12, day: 29 }, end: { month: 12, day: 31 } }] },
    { key: 'interest', name: 'Bank Interest', when: [{ start: { day: 1 }, end: { day: 1 } }] },
    { key: 'electionOver', name: 'Election Over', when: [{ start: { month: 3, day: 27 }, end: { month: 3, day: 27 } }] },
    { key: 'cotfs', name: 'Cult of the Fallen Star', when: [{ start: { day: 7 }, end: { day: 7 } }, { start: { day: 14 }, end: { day: 14 } }, { start: { day: 21 }, end: { day: 21 } }, { start: { day: 28 }, end: { day: 28 } }] },
];

const DWARVEN_KINGS = ['Brammor', 'Emkam', 'Redros', 'Erren', 'Thormyr', 'Emmor', 'Grandan'];

const EVENT_ITEM_OVERRIDES = {
    newYear: { skyblock_id: 'NEW_YEAR_CAKE_BAG', texture_path: '/item/cake', rarity: 'LEGENDARY' },
    spookyFestival: { skyblock_id: 'PET_ITEM_SPOOKY_CUPCAKE', texture_path: '/item/cake', rarity: 'LEGENDARY' },
    zoo: { skyblock_id: 'PET_CAKE', texture_path: '/item/cake', rarity: 'EPIC' },
    jerryWorkshop: { texture_path: '/item/snowball', rarity: 'RARE' },
    winter: { texture_path: '/item/snowball', rarity: 'EPIC' },
    electionOver: { texture_path: '/item/record_11', rarity: 'RARE' },
    interest: { texture_path: '/item/gold_ingot', rarity: 'COMMON' },
    cotfs: { skyblock_id: 'GREAT_SPOOK_ARTIFACT', texture_path: '/item/nether_star', rarity: 'MYTHIC' },
    dark_auction: { skyblock_id: 'DARK_QUEENS_SOUL_DROP', texture_path: '/item/record_13', rarity: 'MYTHIC' },
    jacob: { skyblock_id: 'JACOBS_TICKET', texture_path: '/item/paper', rarity: 'EPIC' },
    dwarven_king: { texture_path: '/item/gold_nugget', rarity: 'RARE' },
};

const selectedCalendarDay = ref(1);
const selectedCalendarMonthAbsolute = ref(0);
const displayedMonthOffset = ref(0);
const expandedTimers = ref(new Set());

const events = [
    {
        key: 'dark-auction',
        name: 'Dark Auction',
        cycleSeconds: 3600,
        activeSeconds: 300,
        offsetSeconds: 55 * 60,
        activeLabel: 'Auction Live',
        upcomingLabel: 'Auction Opens In',
    },
    {
        key: 'jacobs-contest',
        name: "Jacob's Contest",
        cycleSeconds: 3600,
        activeSeconds: 20 * 60,
        offsetSeconds: 15 * 60,
        activeLabel: 'Contest Active',
        upcomingLabel: 'Contest Starts In',
    },
    {
        key: 'traveling-zoo',
        name: 'Traveling Zoo',
        cycleSeconds: 3 * 3600,
        activeSeconds: 60 * 60,
        offsetSeconds: 45 * 60,
        activeLabel: 'Zoo Open',
        upcomingLabel: 'Zoo Arrives In',
    },
    {
        key: 'mythological-ritual',
        name: 'Mythological Ritual',
        cycleSeconds: 2 * 3600,
        activeSeconds: 30 * 60,
        offsetSeconds: 30 * 60,
        activeLabel: 'Ritual Active',
        upcomingLabel: 'Ritual Starts In',
    },
    {
        key: 'spooky-festival',
        name: 'Spooky Festival',
        cycleSeconds: 5 * 24 * 3600,
        activeSeconds: 60 * 60,
        offsetSeconds: 24 * 3600,
        activeLabel: 'Festival Active',
        upcomingLabel: 'Festival Starts In',
    },
];

const timerCards = computed(() => {
    const nowSec = Math.floor(nowMs.value / 1000);

    return events.map((event) => {
        const shifted = nowSec - event.offsetSeconds;
        const cyclePos = ((shifted % event.cycleSeconds) + event.cycleSeconds) % event.cycleSeconds;

        const isActive = cyclePos < event.activeSeconds;
        const inactiveWindow = event.cycleSeconds - event.activeSeconds;

        const secondsUntilStart = isActive ? 0 : (event.cycleSeconds - cyclePos);
        const secondsUntilEnd = isActive
            ? (event.activeSeconds - cyclePos)
            : (event.cycleSeconds - cyclePos + event.activeSeconds);

        const stateTotal = isActive ? event.activeSeconds : inactiveWindow;
        const stateRemaining = isActive ? secondsUntilEnd : secondsUntilStart;
        const progress = stateTotal > 0
            ? Math.max(0, Math.min(1, 1 - (stateRemaining / stateTotal)))
            : 0;

        const nextStart = isActive
            ? nowSec + (event.cycleSeconds - cyclePos)
            : nowSec + secondsUntilStart;
        const nextEnd = nowSec + secondsUntilEnd;

        const nextOccurrences = Array.from({ length: 3 }).map((_, idx) => {
            const startUnix = nextStart + (idx * event.cycleSeconds);
            return {
                startUnix,
                inSeconds: startUnix - nowSec,
            };
        });

        return {
            ...event,
            isActive,
            progress,
            stateRemaining,
            nextStart,
            nextEnd,
            nextOccurrences,
            notifyEnabled: Boolean(notifyEnabled[event.key]),
        };
    });
});

function seasonFromMonth(month) {
    if (month <= 3) return 'Spring';
    if (month <= 6) return 'Summer';
    if (month <= 9) return 'Autumn';
    return 'Winter';
}

function getSkyblockDateFromMs(tsMs) {
    const dayMs = SKYBLOCK_DAY_LENGTH_SECONDS * 1000;
    const monthMs = SKYBLOCK_DAYS_PER_MONTH * dayMs;
    const yearMs = SKYBLOCK_DAYS_PER_YEAR * dayMs;
    const deltaMs = Math.max(0, tsMs - SKYBLOCK_EPOCH_MS);

    const year = Math.floor(deltaMs / yearMs) + 1;
    const withinYear = deltaMs % yearMs;
    const month = Math.floor(withinYear / monthMs) + 1;
    const withinMonth = withinYear % monthMs;
    const day = Math.floor(withinMonth / dayMs) + 1;
    const withinDay = withinMonth % dayMs;

    const skyblockSecond = Math.floor((withinDay / dayMs) * SKYBLOCK_SECONDS_PER_DAY);
    const hour24 = Math.floor(skyblockSecond / 3600) % 24;
    const minute = Math.floor((skyblockSecond % 3600) / 60);

    return {
        year,
        month,
        day,
        hour24,
        minute,
        monthName: SKYBLOCK_MONTH_NAMES[month - 1],
        season: seasonFromMonth(month),
        absoluteDayNumber: Math.floor(deltaMs / dayMs) + 1,
    };
}

function matchesWhen(day, month, range) {
    const startDay = range.start.day ?? day;
    const endDay = range.end.day ?? day;
    const startMonth = range.start.month ?? month;
    const endMonth = range.end.month ?? month;
    return day >= startDay && day <= endDay && month >= startMonth && month <= endMonth;
}

function getEventsForSkyDate(day, month, year) {
    const absoluteDayNumber = ((year - 1) * SKYBLOCK_DAYS_PER_YEAR) + ((month - 1) * SKYBLOCK_DAYS_PER_MONTH) + day;
    const list = [];

    for (const rule of SKYBLOCK_EVENT_RULES) {
        if (rule.when.some((range) => matchesWhen(day, month, range))) {
            list.push({ key: rule.key, name: rule.name });
        }
    }

    if (absoluteDayNumber % 3 === 0) {
        list.push({ key: 'dark_auction', name: 'Dark Auction' });
    }

    if (absoluteDayNumber % 3 === 1) {
        list.push({ key: 'jacob', name: "Jacob's Event" });
    }

    const kingIndex = (5 + absoluteDayNumber) % DWARVEN_KINGS.length;
    list.push({ key: 'dwarven_king', name: `King ${DWARVEN_KINGS[kingIndex]}` });

    return list;
}

function shiftSkyDateByDays(day, month, year, deltaDays) {
    let d = day;
    let m = month;
    let y = year;
    let steps = deltaDays;

    while (steps > 0) {
        d += 1;
        if (d > SKYBLOCK_DAYS_PER_MONTH) {
            d = 1;
            m += 1;
            if (m > SKYBLOCK_MONTHS_PER_YEAR) {
                m = 1;
                y += 1;
            }
        }
        steps -= 1;
    }

    while (steps < 0) {
        d -= 1;
        if (d < 1) {
            d = SKYBLOCK_DAYS_PER_MONTH;
            m -= 1;
            if (m < 1) {
                m = SKYBLOCK_MONTHS_PER_YEAR;
                y -= 1;
            }
        }
        steps += 1;
    }

    return { day: d, month: m, year: y };
}

function pickPrimaryEvent(eventsForDay) {
    const priority = [
        'newYear',
        'winter',
        'jerryWorkshop',
        'spookyFestival',
        'zoo',
        'electionOver',
        'cotfs',
        'dark_auction',
        'jacob',
        'interest',
        'dwarven_king',
    ];

    for (const key of priority) {
        const found = eventsForDay.find((e) => e.key === key);
        if (found) return found;
    }

    return eventsForDay[0] ?? null;
}

const currentSkyblockDate = computed(() => getSkyblockDateFromMs(nowMs.value));

const currentSkyblockMonthAbsolute = computed(() => {
    return ((currentSkyblockDate.value.year - 1) * SKYBLOCK_MONTHS_PER_YEAR) + (currentSkyblockDate.value.month - 1);
});

const currentSkyblockAbsoluteDay = computed(() => {
    return currentSkyblockDate.value.absoluteDayNumber;
});

const currentSkyblockDay = computed(() => {
    return currentSkyblockDate.value.day;
});

const displayedMonthAbsolute = computed(() => {
    return currentSkyblockMonthAbsolute.value + displayedMonthOffset.value;
});

const displayedCalendarYear = computed(() => {
    return Math.floor(displayedMonthAbsolute.value / SKYBLOCK_MONTHS_PER_YEAR) + 1;
});

const displayedCalendarMonth = computed(() => {
    return ((displayedMonthAbsolute.value % SKYBLOCK_MONTHS_PER_YEAR) + SKYBLOCK_MONTHS_PER_YEAR) % SKYBLOCK_MONTHS_PER_YEAR + 1;
});

const displayedMonthLabel = computed(() => {
    const date = currentSkyblockDate.value;
    const hour12 = date.hour24 % 12 === 0 ? 12 : (date.hour24 % 12);
    const period = date.hour24 >= 12 ? 'pm' : 'am';
    return `Time: ${String(hour12).padStart(2, '0')}:${String(date.minute).padStart(2, '0')}${period} ${date.day}/${date.month}/${date.year} ${date.season}`;
});

const calendarSlots = computed(() => {
    const slots = Array.from({ length: 54 }, () => ({ item: null, day: null, special: null }));
    const month = displayedCalendarMonth.value;
    const year = displayedCalendarYear.value;

    for (let day = 1; day <= SKYBLOCK_DAYS_PER_MONTH; day++) {
        const zero = day - 1;
        const row = Math.floor(zero / 7) + 1;
        const col = (zero % 7) + 1;
        const index = row * 9 + col;
        const shiftedDate = shiftSkyDateByDays(day, month, year, SKYBLOCK_CALENDAR_EVENT_DAY_OFFSET);
        const eventsForDay = getEventsForSkyDate(shiftedDate.day, shiftedDate.month, shiftedDate.year);
        const primary = pickPrimaryEvent(eventsForDay);
        const override = primary ? EVENT_ITEM_OVERRIDES[primary.key] : null;

        const item = {
            name: primary ? primary.name : `SkyBlock Day ${day}`,
            count: 1,
            rarity: override?.rarity ?? 'COMMON',
            skyblock_id: override?.skyblock_id ?? null,
            texture_path: override?.texture_path ?? '/item/map_empty',
            lore_html: [
                `§7Date: §e${day}/${month}/${year}`,
                eventsForDay.length > 0 ? `§6${eventsForDay.map((e) => e.name).join(', ')}` : '§8No major event scheduled',
                '§7Daily market and profile activity',
            ],
        };

        slots[index] = {
            item,
            day,
            special: primary,
        };
    }

    return slots;
});

function formatDuration(totalSeconds) {
    const sec = Math.max(0, Math.floor(totalSeconds));
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;

    if (h > 0) {
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }

    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

function formatCompactDuration(totalSeconds) {
    const sec = Math.max(0, Math.floor(totalSeconds));
    const d = Math.floor(sec / 86400);
    const h = Math.floor((sec % 86400) / 3600);
    const m = Math.floor((sec % 3600) / 60);

    if (d > 0) return `${d}d ${h}h`;
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
}

function formatClock(unixSeconds) {
    return new Intl.DateTimeFormat('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
        timeZone: localTz.value,
    }).format(new Date(unixSeconds * 1000));
}

function strokeOffset(progress) {
    const clamped = Math.max(0, Math.min(1, progress));
    const circumference = 2 * Math.PI * 52;
    return circumference * (1 - clamped);
}

function isTimerExpanded(eventKey) {
    return expandedTimers.value.has(eventKey);
}

function toggleTimerExpanded(eventKey) {
    const next = new Set(expandedTimers.value);
    if (next.has(eventKey)) {
        next.delete(eventKey);
    } else {
        next.add(eventKey);
    }
    expandedTimers.value = next;
}

function selectCalendarDay(day) {
    if (!day) return;
    selectedCalendarDay.value = day;
    selectedCalendarMonthAbsolute.value = displayedMonthAbsolute.value;
}

function selectPreviousMonth() {
    displayedMonthOffset.value -= 1;
}

function selectNextMonth() {
    displayedMonthOffset.value += 1;
}

function jumpToCurrentMonth() {
    displayedMonthOffset.value = 0;
    selectedCalendarDay.value = currentSkyblockDay.value;
    selectedCalendarMonthAbsolute.value = currentSkyblockMonthAbsolute.value;
}

function setSelectedFromAbsoluteDay(absoluteDay) {
    const monthAbsolute = Math.floor(absoluteDay / SKYBLOCK_DAYS_PER_MONTH);
    const day = ((absoluteDay % SKYBLOCK_DAYS_PER_MONTH) + SKYBLOCK_DAYS_PER_MONTH) % SKYBLOCK_DAYS_PER_MONTH + 1;

    selectedCalendarMonthAbsolute.value = monthAbsolute;
    selectedCalendarDay.value = day;
    displayedMonthOffset.value = monthAbsolute - currentSkyblockMonthAbsolute.value;
}

function selectPreviousDay() {
    const currentAbsolute = (selectedCalendarMonthAbsolute.value * SKYBLOCK_DAYS_PER_MONTH) + (selectedCalendarDay.value - 1);
    setSelectedFromAbsoluteDay(currentAbsolute - 1);
}

function selectNextDay() {
    const currentAbsolute = (selectedCalendarMonthAbsolute.value * SKYBLOCK_DAYS_PER_MONTH) + (selectedCalendarDay.value - 1);
    setSelectedFromAbsoluteDay(currentAbsolute + 1);
}

async function onPacksChanged(packIds) {
    await setEnabledPacks(packIds);
    textureVersion.value++;
}

function loadNotifyPrefs() {
    try {
        const raw = localStorage.getItem(NOTIFY_PREFS_KEY);
        const keys = raw ? JSON.parse(raw) : [];
        for (const event of events) {
            notifyEnabled[event.key] = Array.isArray(keys) ? keys.includes(event.key) : false;
        }
    } catch {
        for (const event of events) {
            notifyEnabled[event.key] = false;
        }
    }
}

function saveNotifyPrefs() {
    const enabledKeys = events.filter((e) => notifyEnabled[e.key]).map((e) => e.key);
    localStorage.setItem(NOTIFY_PREFS_KEY, JSON.stringify(enabledKeys));
}

function loadSentNotificationKeys() {
    try {
        const raw = localStorage.getItem(NOTIFY_SENT_KEY);
        const keys = raw ? JSON.parse(raw) : [];
        sentKeys.value = new Set(Array.isArray(keys) ? keys : []);
    } catch {
        sentKeys.value = new Set();
    }
}

function saveSentNotificationKeys() {
    localStorage.setItem(NOTIFY_SENT_KEY, JSON.stringify([...sentKeys.value].slice(-200)));
}

function buildSentKey(eventKey, startUnix) {
    return `${eventKey}:${startUnix}`;
}

async function registerEventTimerSw() {
    if (!('serviceWorker' in navigator)) return;

    try {
        swRegistration.value = await navigator.serviceWorker.register('/event-timer-sw.js', {
            scope: '/',
        });
    } catch {
        swRegistration.value = null;
    }
}

async function requestNotificationPermission() {
    if (typeof Notification === 'undefined') {
        notificationPermission.value = 'unsupported';
        return false;
    }

    if (Notification.permission === 'granted') {
        notificationPermission.value = 'granted';
        return true;
    }

    if (Notification.permission === 'denied') {
        notificationPermission.value = 'denied';
        return false;
    }

    const result = await Notification.requestPermission();
    notificationPermission.value = result;
    return result === 'granted';
}

async function scheduleTimestampTrigger(eventCard) {
    if (!swRegistration.value || !supportsTimestampTrigger.value) return;

    const fireAt = (eventCard.nextStart - NOTIFY_LEAD_SECONDS) * 1000;
    if (fireAt <= Date.now()) return;

    const trigger = new window.TimestampTrigger(fireAt);
    await swRegistration.value.showNotification(`${eventCard.name} starts soon`, {
        body: `${eventCard.name} starts in 5 minutes.`,
        tag: `event-timer-${eventCard.key}`,
        renotify: false,
        timestamp: fireAt,
        showTrigger: trigger,
        data: {
            eventKey: eventCard.key,
            startUnix: eventCard.nextStart,
        },
    });
}

async function notifyNow(title, body, tag, data = {}) {
    if (swRegistration.value?.active) {
        swRegistration.value.active.postMessage({
            type: 'SHOW_NOTIFICATION',
            payload: { title, body, tag, data },
        });
        return;
    }

    if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
        new Notification(title, { body, tag, data });
    }
}

async function toggleNotify(eventCard) {
    const enabled = !notifyEnabled[eventCard.key];

    if (enabled) {
        const granted = await requestNotificationPermission();
        if (!granted) return;
    }

    notifyEnabled[eventCard.key] = enabled;
    saveNotifyPrefs();

    if (enabled) {
        try {
            await scheduleTimestampTrigger(eventCard);
        } catch {
            // Fallback timer loop handles unsupported trigger scheduling.
        }
    }
}

function runNotificationTick() {
    if (notificationPermission.value !== 'granted') return;

    const nowSec = Math.floor(Date.now() / 1000);

    for (const eventCard of timerCards.value) {
        if (!notifyEnabled[eventCard.key]) continue;

        const notifyUnix = eventCard.nextStart - NOTIFY_LEAD_SECONDS;
        const sentKey = buildSentKey(eventCard.key, eventCard.nextStart);

        // In-tab fallback notification when the lead window hits.
        if (nowSec >= notifyUnix && nowSec < notifyUnix + 20 && !sentKeys.value.has(sentKey)) {
            sentKeys.value.add(sentKey);
            saveSentNotificationKeys();

            notifyNow(
                `${eventCard.name} starts soon`,
                `${eventCard.name} starts in 5 minutes.`,
                `event-timer-now-${eventCard.key}`,
                { eventKey: eventCard.key, startUnix: eventCard.nextStart }
            );

            if (supportsTimestampTrigger.value) {
                scheduleTimestampTrigger(eventCard).catch(() => {});
            }
        }
    }
}

onMounted(() => {
    selectedCalendarDay.value = currentSkyblockDay.value;
    selectedCalendarMonthAbsolute.value = currentSkyblockMonthAbsolute.value;

    loadNotifyPrefs();
    loadSentNotificationKeys();
    registerEventTimerSw();
    preloadAllTextures().then(() => {
        textureVersion.value++;
    });

    timerId = setInterval(() => {
        nowMs.value = Date.now();
    }, 1000);

    notifyTickId = setInterval(() => {
        runNotificationTick();
    }, 15000);
});

onBeforeUnmount(() => {
    if (timerId !== null) {
        clearInterval(timerId);
        timerId = null;
    }

    if (notifyTickId !== null) {
        clearInterval(notifyTickId);
        notifyTickId = null;
    }
});
</script>

<template>
    <Head title="Visual Event Timer" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold leading-tight text-white">Visual Event Timer</h2>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-5 rounded-xl border border-border bg-surface-800 px-4 py-3 text-sm text-neutral">
                Event windows are cycle-based timers tuned for quick in-game planning.
                <span class="ml-2 rounded bg-surface-700 px-2 py-0.5 text-[11px] text-white">Timezone: {{ localTz }}</span>
                <span class="ml-2 text-[11px]">
                    Notifications:
                    <b class="text-white">{{ notificationPermission }}</b>
                </span>
            </div>

            <div class="space-y-3">
                <section
                    v-for="event in timerCards"
                    :key="event.key"
                    class="overflow-hidden rounded-2xl border bg-surface-800"
                    :class="event.isActive ? 'border-green-500/70 shadow-[0_8px_30px_rgba(34,197,94,0.12)]' : 'border-border shadow-[0_6px_22px_rgba(0,0,0,0.18)]'"
                >
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-white/[0.03]"
                        @click="toggleTimerExpanded(event.key)"
                    >
                        <div class="relative h-10 w-10 shrink-0">
                            <svg viewBox="0 0 48 48" class="h-full w-full -rotate-90">
                                <circle cx="24" cy="24" r="18" stroke="rgba(148, 163, 184, 0.25)" stroke-width="4" fill="none" />
                                <circle
                                    cx="24"
                                    cy="24"
                                    r="18"
                                    stroke-width="4"
                                    fill="none"
                                    stroke-linecap="round"
                                    :stroke="event.isActive ? '#22c55e' : '#f59e0b'"
                                    :stroke-dasharray="2 * Math.PI * 18"
                                    :stroke-dashoffset="(2 * Math.PI * 18) * (1 - Math.max(0, Math.min(1, event.progress)))"
                                />
                            </svg>
                            <span
                                class="absolute inset-0 m-auto h-2 w-2 rounded-full"
                                :class="event.isActive ? 'bg-green-400' : 'bg-yellow-400'"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="truncate text-sm font-semibold text-white">{{ event.name }}</h3>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                    :class="event.isActive ? 'bg-green-500/15 text-green-300' : 'bg-yellow-500/15 text-yellow-300'"
                                >
                                    {{ event.isActive ? 'Live' : 'Upcoming' }}
                                </span>
                            </div>

                            <p class="mt-0.5 text-xs text-neutral">
                                {{ event.isActive ? event.activeLabel : event.upcomingLabel }}
                            </p>
                        </div>

                        <div class="shrink-0 text-right">
                            <p class="text-sm font-semibold text-white">{{ formatDuration(event.stateRemaining) }}</p>
                            <p class="text-[11px] text-neutral">{{ isTimerExpanded(event.key) ? 'Hide' : 'Details' }}</p>
                        </div>
                    </button>

                    <div v-if="isTimerExpanded(event.key)" class="border-t border-border bg-surface-900/45 px-4 py-3">
                        <div class="grid grid-cols-1 gap-3 lg:grid-cols-[1fr_auto] lg:items-start">
                            <div>
                                <p class="text-xs text-neutral">
                                    Next start: <span class="text-white">{{ formatClock(event.nextStart) }}</span>
                                    <span class="mx-2 text-neutral/60">•</span>
                                    Next end: <span class="text-white">{{ formatClock(event.nextEnd) }}</span>
                                </p>

                                <div class="mt-2 rounded-lg border border-border bg-surface-900/65 px-3 py-2">
                                    <div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-neutral">Next Occurrences</div>
                                    <div class="space-y-1 text-xs text-neutral">
                                        <div
                                            v-for="occ in event.nextOccurrences"
                                            :key="occ.startUnix"
                                            class="flex items-center justify-between"
                                        >
                                            <span>in <span class="text-white">{{ formatCompactDuration(occ.inSeconds) }}</span></span>
                                            <span class="text-white/80">{{ formatClock(occ.startUnix) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button
                                class="rounded-md border px-2.5 py-1.5 text-[11px] font-semibold transition"
                                :class="event.notifyEnabled
                                    ? 'border-green-400/50 bg-green-500/10 text-green-300'
                                    : 'border-border bg-surface-700 text-neutral hover:text-white'"
                                @click="toggleNotify(event)"
                            >
                                {{ event.notifyEnabled ? 'Notify me: ON' : 'Notify me' }}
                            </button>
                        </div>
                    </div>
                </section>
            </div>

            <div class="mt-8">
                <div class="mb-3 flex items-center justify-end">
                    <PackSelector @update:packs="onPacksChanged" />
                </div>

                <div class="mb-2 text-center text-2xl font-semibold text-white/90">{{ displayedMonthLabel }}</div>

                <div class="mx-auto flex w-fit items-center justify-center gap-3 sm:gap-4">
                    <button
                        type="button"
                        class="calendar-side-arrow"
                        aria-label="Previous month"
                        @click="selectPreviousMonth"
                    >
                        ◀
                    </button>

                    <div class="min-w-0">
                        <div class="inventory-container">
                            <div class="inventory-grid">
                                <button
                                    v-for="(slot, idx) in calendarSlots"
                                    :key="idx"
                                    type="button"
                                    class="calendar-slot-btn"
                                    :class="{
                                        'calendar-slot-selected': slot.day && slot.day === selectedCalendarDay && selectedCalendarMonthAbsolute === displayedMonthAbsolute,
                                        'calendar-slot-today': slot.day && slot.day === currentSkyblockDay && displayedMonthAbsolute === currentSkyblockMonthAbsolute,
                                        'calendar-slot-today-selected': slot.day && slot.day === currentSkyblockDay && selectedCalendarDay === slot.day && displayedMonthAbsolute === currentSkyblockMonthAbsolute && selectedCalendarMonthAbsolute === displayedMonthAbsolute,
                                    }"
                                    @click="selectCalendarDay(slot.day)"
                                >
                                    <ItemSlot :item="slot.item" />
                                    <span v-if="slot.day" class="calendar-day-badge">{{ slot.day }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="calendar-side-arrow"
                        aria-label="Next month"
                        @click="selectNextMonth"
                    >
                        ▶
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.calendar-slot-btn {
    all: unset;
    position: relative;
    display: block;
    cursor: pointer;
    border-radius: 6px;
    transition: transform 140ms ease, filter 140ms ease;
}

.calendar-slot-btn:hover {
    transform: translateY(-1px);
    filter: brightness(1.04);
}

.calendar-slot-btn:focus-visible {
    outline: 2px solid rgba(255, 255, 255, 0.7);
    outline-offset: 1px;
}

.calendar-slot-selected {
    box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.58);
}

.calendar-slot-today {
    box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.82), 0 0 12px rgba(34, 197, 94, 0.35);
}

.calendar-slot-today-selected {
    box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.96), 0 0 14px rgba(34, 197, 94, 0.42);
}

.calendar-day-badge {
    position: absolute;
    top: 2px;
    right: 4px;
    font-size: 10px;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.9);
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.85);
    pointer-events: none;
}

.calendar-side-arrow {
    display: inline-flex;
    height: 44px;
    width: 44px;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 1px solid rgba(148, 163, 184, 0.35);
    background: rgba(15, 23, 42, 0.55);
    color: rgba(255, 255, 255, 0.9);
    font-size: 16px;
    font-weight: 700;
    transition: transform 120ms ease, background 120ms ease, border-color 120ms ease;
}

.calendar-side-arrow:hover {
    transform: translateY(-1px);
    background: rgba(30, 41, 59, 0.8);
    border-color: rgba(255, 255, 255, 0.45);
}

.calendar-side-arrow:focus-visible {
    outline: 2px solid rgba(34, 197, 94, 0.75);
    outline-offset: 2px;
}
</style>
