<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps({
    canLogin: { type: Boolean, default: false },
    canRegister: { type: Boolean, default: false },
    laravelVersion: { type: String, required: false },
    phpVersion: { type: String, required: false },
});

const searchUsername = ref('');

const featureCards = [
    {
        id: 'bazaar',
        title: 'Bazaar Flip Scanner',
        subtitle: 'Real-time spread and margin tracking',
        description: 'Filter by true profit, volume and margin to find stable flips in seconds.',
        metric: 'Live market deltas',
        routeName: 'bazaar',
        accent: 'emerald',
        layout: 'main',
    },
    {
        id: 'events',
        title: 'Event Timer',
        subtitle: 'Cycle-accurate planning',
        description: 'Track Dark Auction, Jacob, Zoo and mayor-boosted windows with countdown precision.',
        metric: 'Always on schedule',
        routeName: 'event-timer',
        accent: 'indigo',
        layout: 'sidebar',
    },
    {
        id: 'npc',
        title: 'NPC Arbitrage',
        subtitle: 'Low-risk coin printing',
        description: 'Instantly compare Bazaar buy prices with NPC sell values and rank best opportunities.',
        metric: '1.5x+ opportunities',
        routeName: 'npc-flips',
        accent: 'amber',
        layout: 'twin',
    },
    {
        id: 'profiles',
        title: 'Profile Stats',
        subtitle: 'SkyCrypt style analytics',
        description: 'Inspect gear, skills, pets, dungeons and collections in one optimized panel.',
        metric: 'Deep account visibility',
        routeName: 'profile-stats',
        accent: 'emerald',
        layout: 'twin',
    },
    {
        id: 'mayors',
        title: 'Mayor Intelligence',
        subtitle: 'Election and perk awareness',
        description: 'See active mayor effects, candidate groups and strategic impact on money routes.',
        metric: 'Meta-ready decisions',
        routeName: 'mayors',
        accent: 'indigo',
        layout: 'main',
    },
    {
        id: 'privacy',
        title: 'Privacy-First Data Layer',
        subtitle: 'Community built tooling',
        description: 'API-driven features with no session-id logging in analytics flow.',
        metric: '100% API driven',
        routeName: 'profile-stats',
        accent: 'amber',
        layout: 'sidebar',
    },
];

function submitSearch() {
    const username = searchUsername.value.trim();
    if (!username) {
        return;
    }

    router.get(route('profile-stats'), { username });
}

function cardLayoutClass(layout) {
    if (layout === 'main') return 'md:col-span-4';
    if (layout === 'sidebar') return 'md:col-span-2';
    return 'md:col-span-3';
}

function cardAccentClass(accent) {
    if (accent === 'emerald') return 'card-accent-emerald';
    if (accent === 'amber') return 'card-accent-amber';
    return 'card-accent-indigo';
}
</script>

<template>
    <Head title="SkyblockHub" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <section class="mb-14">
                    <h1 class="mx-auto max-w-5xl text-center text-5xl font-black leading-[1.05] tracking-tight text-white sm:text-6xl lg:text-8xl">
                        Skyblock Intelligence. Simplified
                    </h1>

                    <p class="mx-auto mt-6 max-w-2xl text-center text-base leading-relaxed text-white/60 sm:text-lg lg:text-xl">
                        Real-time Bazaar tracking, profile analytics, and event countdowns. Everything you need to dominate the Hub, all in one place.
                    </p>
                </section>

                <section class="mb-10">
                    <div class="mx-auto w-full max-w-2xl rounded-2xl border border-border/80 bg-surface-900/75 p-3 shadow-[0_16px_40px_rgba(0,0,0,0.35)] backdrop-blur-sm">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <div class="relative flex-1">
                                <svg class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M8.5 3a5.5 5.5 0 104.35 8.87l2.64 2.64a1 1 0 001.42-1.42l-2.64-2.64A5.5 5.5 0 008.5 3zm-3.5 5.5a3.5 3.5 0 117 0 3.5 3.5 0 01-7 0z" clip-rule="evenodd" />
                                </svg>
                                <input
                                    v-model="searchUsername"
                                    type="text"
                                    placeholder="Search player..."
                                    class="w-full rounded-xl border border-border/80 bg-surface-800/80 py-3 pl-11 pr-4 text-sm text-white placeholder:text-neutral/80 transition focus:border-profit/70 focus:outline-none focus:ring-2 focus:ring-profit/25"
                                    @keyup.enter="submitSearch"
                                />
                            </div>
                            <button
                                @click="submitSearch"
                                class="inline-flex h-[46px] items-center justify-center rounded-xl border border-profit/35 bg-profit/20 px-6 text-sm font-semibold text-profit transition hover:bg-profit/30 hover:text-white"
                            >
                                Search
                            </button>
                        </div>
                    </div>
                    <p class="mt-3 text-center text-xs text-white/50">After search, you will be redirected to Profile Stats with the entered username.</p>
                </section>

                <section>
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-white/45">What You Can Use Right Now</h2>
                        <div class="text-xs text-white/40">Interactive overview</div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-6">
                        <Link
                            v-for="card in featureCards"
                            :key="card.id"
                            :href="route(card.routeName)"
                            class="feature-card rounded-2xl border border-white/10 bg-surface-900/70 p-5 backdrop-blur-sm"
                            :class="[cardAccentClass(card.accent), cardLayoutClass(card.layout)]"
                        >
                            <div class="mb-3 inline-flex rounded-full border border-white/15 bg-white/5 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-white/70">
                                {{ card.metric }}
                            </div>

                            <h3 class="text-lg font-bold text-white">{{ card.title }}</h3>
                            <p class="mt-1 text-xs uppercase tracking-[0.1em] text-white/45">{{ card.subtitle }}</p>
                            <p class="mt-3 text-sm leading-relaxed text-white/70">{{ card.description }}</p>

                            <div class="mt-5 flex items-center justify-between text-xs font-semibold">
                                <span class="text-white/45">Open module</span>
                                <span class="text-white/85">Explore -></span>
                            </div>
                        </Link>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.feature-card {
    box-shadow: 0 14px 30px rgba(0, 0, 0, 0.35);
    transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
}

.feature-card:hover {
    transform: translateY(-3px);
    border-color: rgba(255, 255, 255, 0.25);
}

.card-accent-emerald {
    background-image: linear-gradient(180deg, rgba(16, 185, 129, 0.08), rgba(2, 6, 23, 0.78));
}

.card-accent-emerald:hover {
    box-shadow: 0 0 24px rgba(16, 185, 129, 0.16), 0 14px 30px rgba(0, 0, 0, 0.35);
}

.card-accent-amber {
    background-image: linear-gradient(180deg, rgba(251, 191, 36, 0.08), rgba(2, 6, 23, 0.78));
}

.card-accent-amber:hover {
    box-shadow: 0 0 24px rgba(251, 191, 36, 0.16), 0 14px 30px rgba(0, 0, 0, 0.35);
}

.card-accent-indigo {
    background-image: linear-gradient(180deg, rgba(99, 102, 241, 0.08), rgba(2, 6, 23, 0.78));
}

.card-accent-indigo:hover {
    box-shadow: 0 0 24px rgba(99, 102, 241, 0.18), 0 14px 30px rgba(0, 0, 0, 0.35);
}
</style>
