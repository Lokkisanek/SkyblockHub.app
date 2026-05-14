/**
 * SkyBlock event timers + calendar helpers for the dashboard (aligned with EventTimer/Index.vue).
 */

const SKYBLOCK_DAYS_PER_MONTH = 31;
const SKYBLOCK_DAY_LENGTH_SECONDS = 20 * 60;
const SKYBLOCK_SECONDS_PER_DAY = 24 * 60 * 60;
const SKYBLOCK_DAYS_PER_YEAR = SKYBLOCK_DAYS_PER_MONTH * 12;
const SKYBLOCK_EPOCH_MS = new Date('Jun 11 2019 17:55:00 GMT').getTime();

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
    { key: 'mythologicalRitual', name: 'Mythological Ritual', when: [{ start: { day: 1 }, end: { day: 31 } }] },
    { key: 'jerryWorkshop', name: "Jerry's Workshop", when: [{ start: { month: 12, day: 1 }, end: { month: 12, day: 31 } }] },
    { key: 'winter', name: 'Season of Jerry', when: [{ start: { month: 12, day: 24 }, end: { month: 12, day: 26 } }] },
    { key: 'newYear', name: 'New Year Celebration', when: [{ start: { month: 12, day: 29 }, end: { month: 12, day: 31 } }] },
    { key: 'interest', name: 'Bank Interest', when: [{ start: { day: 1 }, end: { day: 1 } }] },
    { key: 'electionOver', name: 'Election Over', when: [{ start: { month: 3, day: 27 }, end: { month: 3, day: 27 } }] },
    { key: 'cotfs', name: 'Cult of the Fallen Star', when: [{ start: { day: 7 }, end: { day: 7 } }, { start: { day: 14 }, end: { day: 14 } }, { start: { day: 21 }, end: { day: 21 } }, { start: { day: 28 }, end: { day: 28 } }] },
];

const DWARVEN_KINGS = ['Brammor', 'Emkam', 'Redros', 'Erren', 'Thormyr', 'Emmor', 'Grandan'];

const EVENTS = [
    {
        key: 'dark-auction',
        name: 'Dark Auction',
        cycleSeconds: 3600,
        activeSeconds: 300,
        offsetSeconds: 55 * 60,
        activeLabel: 'Auction live',
        upcomingLabel: 'Opens in',
    },
    {
        key: 'jacobs-contest',
        name: "Jacob's Contest",
        cycleSeconds: 3600,
        activeSeconds: 20 * 60,
        offsetSeconds: 15 * 60,
        activeLabel: 'Contest active',
        upcomingLabel: 'Starts in',
    },
    {
        key: 'traveling-zoo',
        name: 'Traveling Zoo',
        cycleSeconds: 3 * 3600,
        activeSeconds: 60 * 60,
        offsetSeconds: 45 * 60,
        activeLabel: 'Zoo open',
        upcomingLabel: 'Arrives in',
    },
    {
        key: 'mythological-ritual',
        name: 'Mythological Ritual',
        cycleSeconds: 2 * 3600,
        activeSeconds: 30 * 60,
        offsetSeconds: 30 * 60,
        activeLabel: 'Ritual active',
        upcomingLabel: 'Starts in',
        requiresPerk: 'mythological_ritual',
    },
    {
        key: 'dungeon-rush',
        name: 'Dungeon Rush',
        cycleSeconds: 3600,
        activeSeconds: 15 * 60,
        offsetSeconds: 20 * 60,
        activeLabel: 'Window active',
        upcomingLabel: 'Window in',
        dungeonRelated: true,
    },
    {
        key: 'spooky-festival',
        name: 'Spooky Festival',
        cycleSeconds: SKYBLOCK_DAYS_PER_YEAR * SKYBLOCK_DAY_LENGTH_SECONDS,
        activeSeconds: 3 * SKYBLOCK_DAY_LENGTH_SECONDS,
        offsetSeconds: ((8 * SKYBLOCK_DAYS_PER_MONTH) + 28) * SKYBLOCK_DAY_LENGTH_SECONDS,
        activeLabel: 'Festival active',
        upcomingLabel: 'Starts in',
    },
    {
        key: 'bank-interest',
        name: 'Bank Interest',
        cycleSeconds: SKYBLOCK_DAYS_PER_MONTH * SKYBLOCK_DAY_LENGTH_SECONDS,
        activeSeconds: 15 * 60,
        offsetSeconds: 0,
        activeLabel: 'Payout active',
        upcomingLabel: 'Payout in',
    },
    {
        key: 'cult-fallen-star',
        name: 'Cult of the Fallen Star',
        cycleSeconds: 7 * SKYBLOCK_DAY_LENGTH_SECONDS,
        activeSeconds: 15 * 60,
        offsetSeconds: 0,
        activeLabel: 'Window active',
        upcomingLabel: 'Window in',
    },
    {
        key: 'season-of-jerry',
        name: 'Season of Jerry',
        cycleSeconds: SKYBLOCK_DAYS_PER_YEAR * SKYBLOCK_DAY_LENGTH_SECONDS,
        activeSeconds: 3 * SKYBLOCK_DAY_LENGTH_SECONDS,
        offsetSeconds: ((11 * SKYBLOCK_DAYS_PER_MONTH) + 23) * SKYBLOCK_DAY_LENGTH_SECONDS,
        activeLabel: 'Season active',
        upcomingLabel: 'Starts in',
        requiresPerk: 'jerry_workshop',
    },
    {
        key: 'new-year-celebration',
        name: 'New Year Celebration',
        cycleSeconds: SKYBLOCK_DAYS_PER_YEAR * SKYBLOCK_DAY_LENGTH_SECONDS,
        activeSeconds: 3 * SKYBLOCK_DAY_LENGTH_SECONDS,
        offsetSeconds: ((11 * SKYBLOCK_DAYS_PER_MONTH) + 28) * SKYBLOCK_DAY_LENGTH_SECONDS,
        activeLabel: 'Celebration active',
        upcomingLabel: 'Starts in',
    },
    {
        key: 'election-over',
        name: 'Election Over',
        cycleSeconds: SKYBLOCK_DAYS_PER_YEAR * SKYBLOCK_DAY_LENGTH_SECONDS,
        activeSeconds: SKYBLOCK_DAY_LENGTH_SECONDS,
        offsetSeconds: ((2 * SKYBLOCK_DAYS_PER_MONTH) + 26) * SKYBLOCK_DAY_LENGTH_SECONDS,
        activeLabel: 'Election day',
        upcomingLabel: 'Election day in',
    },
];

function seasonFromMonth(month) {
    if (month <= 3) return 'Spring';
    if (month <= 6) return 'Summer';
    if (month <= 9) return 'Autumn';
    return 'Winter';
}

export function getSkyblockDateFromMs(tsMs) {
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

function isCalendarRuleVisible(ruleKey, activePerks) {
    if (ruleKey === 'mythologicalRitual') {
        return Boolean(activePerks?.mythological_ritual);
    }
    if (ruleKey === 'jerryWorkshop') {
        return Boolean(activePerks?.jerry_workshop);
    }
    return true;
}

export function getEventsForSkyDate(day, month, year, activePerks) {
    const absoluteDayNumber = (year - 1) * SKYBLOCK_DAYS_PER_YEAR + (month - 1) * SKYBLOCK_DAYS_PER_MONTH + day;
    const list = [];

    for (const rule of SKYBLOCK_EVENT_RULES) {
        if (!isCalendarRuleVisible(rule.key, activePerks)) {
            continue;
        }
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

function isEventVisible(event, activePerks) {
    if (!event.requiresPerk) {
        return true;
    }
    return Boolean(activePerks?.[event.requiresPerk]);
}

function isEventBoosted(event, activePerks, boostedKeys) {
    const set = boostedKeys instanceof Set ? boostedKeys : new Set(boostedKeys ?? []);
    if (event.dungeonRelated && activePerks?.dungeon_benefit) {
        return true;
    }
    return set.has(event.key);
}

function buildElectionTimerCard(nowSec, electionTimeline) {
    const timeline = electionTimeline;
    if (!timeline) {
        return null;
    }

    const startUnix = Number(timeline.start_unix ?? 0);
    const endUnix = Number(timeline.end_unix ?? 0);
    const officeUnix = Number(timeline.office_unix ?? 0);

    if (!startUnix || !endUnix || !officeUnix) {
        return null;
    }

    let isActive = false;
    let stateRemaining = 0;
    let stateTotal = 1;
    let nextStart = startUnix;
    let nextEnd = endUnix;
    let activeLabel = 'Election live';
    let upcomingLabel = 'Election starts in';

    if (nowSec < startUnix) {
        stateRemaining = startUnix - nowSec;
        stateTotal = Math.max(1, startUnix - nowSec);
        nextStart = startUnix;
        nextEnd = endUnix;
    } else if (nowSec >= startUnix && nowSec < endUnix) {
        isActive = true;
        stateRemaining = endUnix - nowSec;
        stateTotal = Math.max(1, endUnix - startUnix);
        nextStart = endUnix;
        nextEnd = officeUnix;
    } else if (nowSec >= endUnix && nowSec < officeUnix) {
        stateRemaining = officeUnix - nowSec;
        stateTotal = Math.max(1, officeUnix - endUnix);
        nextStart = officeUnix;
        nextEnd = officeUnix;
        activeLabel = 'Mayor transition';
        upcomingLabel = 'New mayor in';
    } else {
        return null;
    }

    const progress = Math.max(0, Math.min(1, 1 - stateRemaining / stateTotal));

    return {
        key: 'election-cycle',
        name: 'Election cycle',
        isActive,
        progress,
        stateRemaining,
        nextStart,
        nextEnd,
        activeLabel,
        upcomingLabel,
        boosted: false,
    };
}

/** Keys + labels for dashboard event-timer widget (dropdown). */
export function getSelectableTimerEventOptions() {
    return [
        ...EVENTS.map((e) => ({ key: e.key, name: e.name })),
        { key: 'election-cycle', name: 'Election cycle' },
    ];
}

/**
 * @param {number} nowMs
 * @param {object|null} electionTimeline
 * @param {{ active_perks?: Record<string, boolean>, boosted_event_keys?: string[] }|null} perkState
 * @returns {Array<object>}
 */
export function buildDashboardTimerCards(nowMs, electionTimeline, perkState) {
    const nowSec = Math.floor(nowMs / 1000);
    const activePerks = perkState?.active_perks ?? {};
    const boosted = new Set(perkState?.boosted_event_keys ?? []);

    const baseCards = EVENTS.filter((event) => isEventVisible(event, activePerks)).map((event) => {
        const shifted = nowSec - event.offsetSeconds;
        const cyclePos = ((shifted % event.cycleSeconds) + event.cycleSeconds) % event.cycleSeconds;

        const isActive = cyclePos < event.activeSeconds;
        const inactiveWindow = event.cycleSeconds - event.activeSeconds;

        const secondsUntilStart = isActive ? 0 : event.cycleSeconds - cyclePos;
        const secondsUntilEnd = isActive ? event.activeSeconds - cyclePos : event.cycleSeconds - cyclePos + event.activeSeconds;

        const stateTotal = isActive ? event.activeSeconds : inactiveWindow;
        const stateRemaining = isActive ? secondsUntilEnd : secondsUntilStart;
        const progress = stateTotal > 0 ? Math.max(0, Math.min(1, 1 - stateRemaining / stateTotal)) : 0;

        const nextStart = isActive ? nowSec + (event.cycleSeconds - cyclePos) : nowSec + secondsUntilStart;
        const nextEnd = nowSec + secondsUntilEnd;

        return {
            ...event,
            isActive,
            progress,
            stateRemaining,
            nextStart,
            nextEnd,
            boosted: isEventBoosted(event, activePerks, boosted),
        };
    });

    const electionCard = buildElectionTimerCard(nowSec, electionTimeline);
    if (electionCard) {
        baseCards.push(electionCard);
    }

    return baseCards;
}

export function formatDurationSeconds(total) {
    const s = Math.max(0, Math.floor(total));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const r = s % 60;
    if (h > 0) {
        return `${h}:${String(m).padStart(2, '0')}:${String(r).padStart(2, '0')}`;
    }
    return `${m}:${String(r).padStart(2, '0')}`;
}
