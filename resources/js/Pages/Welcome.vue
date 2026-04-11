<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps({
    canLogin: { type: Boolean, default: false },
    canRegister: { type: Boolean, default: false },
    laravelVersion: { type: String, required: false },
    phpVersion: { type: String, required: false },
});

const searchUsername = ref('');
const siteOrigin = typeof window !== 'undefined' ? window.location.origin : 'http://localhost:8000';
const canonicalUrl = `${siteOrigin}/`;
const pageTitle = 'Hypixel SkyBlock Tools - Bazaar Flips, NPC Arbitrage, Profiles';
const pageDescription = 'SkyblockHub is a clean Hypixel SkyBlock dashboard for Bazaar flips, NPC arbitrage, profile analysis, mayor perks, and event timing.';

const featureCards = [
    {
        id: 'bazaar',
        title: 'Bazaar Flips',
        subtitle: 'Real-time spreads & margins',
        description: 'Find the most profitable Bazaar flips instantly with live market data and volume indicators.',
        icon: '📊',
        routeName: 'bazaar',
        accent: 'emerald',
    },
    {
        id: 'npc',
        title: 'NPC Arbitrage',
        subtitle: 'Buy low, sell to NPC',
        description: 'Compare Bazaar buy prices vs NPC sell values in one table. Easy, low-risk coins.',
        icon: '🪙',
        routeName: 'npc-flips',
        accent: 'amber',
    },
    {
        id: 'profiles',
        title: 'Profile Stats',
        subtitle: 'Full account breakdown',
        description: 'Inspect gear, skills, pets, dungeons, collections, and networth for any player.',
        icon: '🔍',
        routeName: 'profile-stats',
        accent: 'emerald',
    },
    {
        id: 'events',
        title: 'Event Timer',
        subtitle: 'Never miss a cycle',
        description: 'Track SkyBlock events with clear countdowns so you always show up on time.',
        icon: '⏱️',
        routeName: 'event-timer',
        accent: 'indigo',
    },
    {
        id: 'mayors',
        title: 'Mayor Intel',
        subtitle: 'Perks & election context',
        description: 'See the current mayor\'s active perks and plan around the upcoming election.',
        icon: '🏛️',
        routeName: 'mayors',
        accent: 'indigo',
    },
];

function submitSearch() {
    const username = searchUsername.value.trim();
    if (!username) {
        return;
    }

    router.get(route('profile-stats'), { username });
}

function cardAccentClass(accent) {
    if (accent === 'emerald') return 'card-accent-emerald';
    if (accent === 'amber') return 'card-accent-amber';
    return 'card-accent-indigo';
}
</script>

<template>
    <Head>
        <title>{{ pageTitle }}</title>
        <meta head-key="description" name="description" :content="pageDescription" />
        <meta head-key="robots" name="robots" content="index,follow" />
        <link head-key="canonical" rel="canonical" :href="canonicalUrl" />
        <meta head-key="og:title" property="og:title" :content="`${pageTitle} - SkyblockHub`" />
        <meta head-key="og:description" property="og:description" :content="pageDescription" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:image" property="og:image" :content="`${siteOrigin}/img/logo-white.webp`" />
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" :content="`${pageTitle} - SkyblockHub`" />
        <meta head-key="twitter:description" name="twitter:description" :content="pageDescription" />
    </Head>

    <AuthenticatedLayout>
        <div class="pt-14 pb-20 sm:pt-16 sm:pb-24 lg:pt-20 lg:pb-28">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <section class="animate-rise-up animate-delay-1 mb-12">
                    <div class="mx-auto mb-6 flex w-fit items-center gap-3 rounded-full border border-white/10 bg-white/5 px-4 py-2 backdrop-blur-sm">
                        <ApplicationLogo tone="light" class="h-7 w-7 shrink-0" />
                        <span class="text-[10px] font-bold uppercase tracking-[0.24em] text-white/75">SkyblockHub</span>
                    </div>

                    <h1 class="mx-auto max-w-6xl text-center text-6xl font-black leading-[1.02] tracking-tight text-white sm:text-7xl lg:text-8xl">
                        Make better SkyBlock decisions faster
                    </h1>

                    <p class="mx-auto mt-6 max-w-3xl text-center text-lg leading-relaxed text-white/80 sm:text-xl lg:text-2xl">
                        Real-time Bazaar flips, NPC arbitrage, AI-powered signals, and profile intel — all in one place. Zero ads, zero bloat.
                    </p>
                </section>

                <section class="animate-rise-up animate-delay-2 mb-10">
                    <div class="mx-auto w-full max-w-3xl rounded-2xl border border-border/80 bg-surface-900/75 p-3 shadow-[0_16px_40px_rgba(0,0,0,0.35)] backdrop-blur-sm">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <div class="relative flex-1">
                                <div class="pointer-events-none absolute left-4 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full border border-white/10 bg-white/5 shadow-[0_0_0_1px_rgba(255,255,255,0.02)]">
                                    <ApplicationLogo tone="light" class="h-4 w-4" />
                                </div>
                                <input
                                    v-model="searchUsername"
                                    type="text"
                                    placeholder="Search SkyBlock player..."
                                    class="w-full rounded-xl border border-border/80 bg-surface-800/80 py-3 pl-14 pr-4 text-base text-white placeholder:text-neutral/80 transition focus:border-profit/70 focus:outline-none focus:ring-2 focus:ring-profit/25"
                                    @keyup.enter="submitSearch"
                                />
                            </div>
                            <button
                                @click="submitSearch"
                                class="inline-flex h-[46px] items-center justify-center rounded-xl border border-profit/35 bg-profit/20 px-6 text-base font-semibold text-profit transition hover:bg-profit/30 hover:text-white"
                            >
                                Open profile
                            </button>
                        </div>
                    </div>
                    <p class="mt-3 text-center text-sm text-white/65">Enter a username and jump straight into profile stats.</p>
                </section>

                <section class="animate-rise-up animate-delay-3 mb-12">
                    <div class="mx-auto max-w-5xl">
                        <!-- Discord Login CTA (guests) -->
                        <div v-if="canLogin" class="mb-10 rounded-2xl border border-[#5865F2]/25 bg-[#5865F2]/[0.06] p-8 text-center shadow-[0_16px_48px_rgba(88,101,242,0.08)] backdrop-blur-sm sm:p-10">
                            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl border border-[#5865F2]/30 bg-[#5865F2]/15">
                                <svg class="h-8 w-8 text-[#5865F2]" viewBox="0 0 24 24" fill="currentColor"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
                            </div>
                            <h2 class="text-2xl font-bold text-white sm:text-3xl">Get started in seconds</h2>
                            <p class="mx-auto mt-3 max-w-lg text-base text-white/60">Connect your Discord account to unlock the dashboard, start a free trial, and access every tool.</p>
                            <a
                                :href="route('auth.discord')"
                                class="mt-6 inline-flex items-center justify-center gap-3 rounded-xl border border-[#5865F2]/50 bg-[#5865F2] px-8 py-3.5 text-base font-bold text-white shadow-[0_4px_20px_rgba(88,101,242,0.35)] transition hover:bg-[#4752C4] hover:shadow-[0_4px_24px_rgba(88,101,242,0.5)]"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
                                Log in with Discord
                            </a>
                        </div>
                        <!-- Logged-in CTA -->
                        <div v-else class="mb-10 text-center">
                            <Link
                                :href="route('dashboard')"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-profit/40 bg-profit/15 px-8 py-4 text-lg font-semibold text-profit transition hover:bg-profit/30 hover:text-white"
                            >
                                Open Dashboard
                            </Link>
                        </div>

                        <!-- Pricing Cards -->
                        <div class="grid gap-6 md:grid-cols-2">
                            <!-- VIP -->
                            <div class="rounded-2xl border border-white/10 bg-surface-900/75 p-6 shadow-[0_16px_40px_rgba(0,0,0,0.25)] backdrop-blur-sm">
                                <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-emerald-400/80">VIP</p>
                                <p class="mt-3 text-4xl font-black text-white">$4.99<span class="text-lg font-medium text-white/45">/month</span></p>
                                <ul class="mt-5 space-y-2.5 text-sm leading-relaxed text-white/75">
                                    <li class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-emerald-400/70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                        Top 3 flips highlighted
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-emerald-400/70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                        Faster refresh rate
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-emerald-400/70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                        Priority widget updates
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-emerald-400/70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                        VIP tag in leaderboards
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-emerald-400/70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                        +2 dashboard slots
                                    </li>
                                </ul>
                                <Link
                                    :href="route('billing')"
                                    class="mt-6 flex w-full items-center justify-center rounded-xl border border-emerald-400/30 bg-emerald-500/10 py-3 text-sm font-semibold text-emerald-400 transition hover:bg-emerald-500/20 hover:text-emerald-300"
                                >
                                    Subscribe VIP
                                </Link>
                            </div>

                            <!-- MVP (highlighted) -->
                            <div class="relative rounded-2xl border border-amber-400/25 bg-surface-900/75 p-6 shadow-[0_16px_40px_rgba(0,0,0,0.25),0_0_60px_rgba(251,191,36,0.06)] backdrop-blur-sm">
                                <span class="absolute -top-3 right-5 rounded-full border border-amber-400/40 bg-amber-500/15 px-3 py-1 text-[10px] font-bold uppercase tracking-widest text-amber-400">7-Day Free Trial</span>
                                <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-amber-400/80">MVP</p>
                                <p class="mt-3 text-4xl font-black text-white">$8.99<span class="text-lg font-medium text-white/45">/month</span></p>
                                <ul class="mt-5 space-y-2.5 text-sm leading-relaxed text-white/75">
                                    <li class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-amber-400/70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                        Everything from VIP
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-amber-400/70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                        AI-controlled flips section
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-amber-400/70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                        AI trust score &amp; risk signals
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-amber-400/70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                        MVP tag in leaderboards
                                    </li>
                                </ul>
                                <Link
                                    :href="route('billing')"
                                    class="mt-6 flex w-full items-center justify-center rounded-xl border border-amber-400/40 bg-amber-500/15 py-3 text-sm font-bold text-amber-400 transition hover:bg-amber-500/25 hover:text-amber-300"
                                >
                                    Start 7-Day Free Trial
                                </Link>
                                <Link
                                    :href="route('billing')"
                                    class="mt-2 flex w-full items-center justify-center rounded-xl border border-white/10 bg-white/5 py-3 text-sm font-semibold text-white/70 transition hover:bg-white/10 hover:text-white"
                                >
                                    Subscribe MVP
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="animate-rise-up animate-delay-4">
                    <div class="mb-6 text-center">
                        <h2 class="text-xl font-bold text-white sm:text-2xl">Everything you need, free to use</h2>
                        <p class="mt-2 text-sm text-white/50">All core modules are available without an account.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Link
                            v-for="(card, index) in featureCards"
                            :key="card.id"
                            :href="route(card.routeName)"
                            class="feature-card group flex flex-col justify-between rounded-2xl border border-white/[0.07] p-6"
                            :class="[cardAccentClass(card.accent), `animate-rise-up animate-delay-card-${index + 1}`]"
                        >
                            <div>
                                <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-lg">
                                    {{ card.icon }}
                                </div>

                                <h3 class="text-lg font-bold text-white">{{ card.title }}</h3>
                                <p class="mt-1 text-xs font-medium uppercase tracking-[0.16em] text-white/40">{{ card.subtitle }}</p>
                                <p class="mt-3 text-sm leading-relaxed text-white/60">{{ card.description }}</p>
                            </div>

                            <div class="mt-5 flex items-center gap-1.5 text-xs font-semibold text-white/40 transition group-hover:text-white/80">
                                Open module
                                <svg class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
                            </div>
                        </Link>
                    </div>
                </section>
            </div>
        </div>

        <footer class="footer-wrapper relative">
            <div class="slime-glow-footer-container">
                <div class="slime-glow-footer"></div>
            </div>

            <div class="relative z-10 mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 gap-8 sm:grid-cols-4">
                    <div class="col-span-2 sm:col-span-1">
                        <Link :href="route('dashboard')" class="flex items-center gap-2 text-sm font-bold tracking-wide text-white">
                            <ApplicationLogo tone="light" class="h-7 w-7 shrink-0" />
                            <span>SkyblockHub</span>
                        </Link>
                        <p class="mt-2 text-xs leading-relaxed text-white/30">Skyblock intelligence platform. Real-time data, zero ads.</p>
                    </div>

                    <div>
                        <h3 class="text-[10px] font-bold uppercase tracking-widest text-white/40">Modules</h3>
                        <ul class="mt-3 space-y-2">
                            <li><Link :href="route('bazaar')" class="text-xs text-white/35 transition hover:text-white">Bazaar Flips</Link></li>
                            <li><Link :href="route('npc-flips')" class="text-xs text-white/35 transition hover:text-white">NPC Arbitrage</Link></li>
                            <li><Link :href="route('event-timer')" class="text-xs text-white/35 transition hover:text-white">Event Timer</Link></li>
                            <li><Link :href="route('mayors')" class="text-xs text-white/35 transition hover:text-white">Mayor Intel</Link></li>
                            <li><Link :href="route('profile-stats')" class="text-xs text-white/35 transition hover:text-white">Profile Stats</Link></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-[10px] font-bold uppercase tracking-widest text-white/40">Project</h3>
                        <ul class="mt-3 space-y-2">
                            <li><Link :href="route('about')" class="text-xs text-white/35 transition hover:text-white">About</Link></li>
                            <li><a href="https://github.com/Lokkisanek/SkyblockHub.play" target="_blank" rel="noopener noreferrer" class="text-xs text-white/35 transition hover:text-white">GitHub</a></li>
                            <li><a href="https://www.patreon.com/SkyblockHub" target="_blank" rel="noopener noreferrer" class="text-xs text-white/35 transition hover:text-white">Patreon</a></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-[10px] font-bold uppercase tracking-widest text-white/40">Legal</h3>
                        <ul class="mt-3 space-y-2">
                            <li><Link :href="route('privacy')" class="text-xs text-white/35 transition hover:text-white">Privacy Policy</Link></li>
                            <li><Link :href="route('terms')" class="text-xs text-white/35 transition hover:text-white">Terms of Service</Link></li>
                        </ul>
                    </div>
                </div>

                <div class="mt-8 flex flex-col items-center justify-between gap-3 border-t border-white/[0.06] pt-6 sm:flex-row">
                    <p class="text-[11px] text-white/25">&copy; {{ new Date().getFullYear() }} SkyblockHub. All rights reserved.</p>
                    <p class="text-[11px] text-white/20">Not affiliated with Hypixel Inc. or Mojang Studios.</p>
                </div>
            </div>
        </footer>
    </AuthenticatedLayout>
</template>

<style scoped>
.footer-wrapper {
    background: linear-gradient(180deg, rgba(16, 16, 16, 0.95) 0%, rgba(16, 16, 16, 0.88) 100%);
    backdrop-filter: blur(16px);
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    z-index: 10;
}

.slime-glow-footer-container {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    overflow: hidden;
    pointer-events: none;
    z-index: 1;
}

.slime-glow-footer {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 120px;
    height: 50px;
    border-radius: 4px;
    background: rgba(93, 211, 93, 0.08);
    filter: blur(35px);
    pointer-events: none;
    animation: footerSlimeDrift 40s ease-in-out infinite, footerSlimeBounce 5s ease-in-out infinite;
}

.slime-glow-footer::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 60px;
    height: 40px;
    border-radius: 3px;
    background: rgba(61, 168, 61, 0.06);
    filter: blur(20px);
    animation: footerSlimePulse 6s ease-in-out infinite;
}

@keyframes footerSlimeDrift {
    0%, 100% { left: 75%; }
    33% { left: 40%; }
    66% { left: 10%; }
}

@keyframes footerSlimeBounce {
    0%, 100% { transform: translateY(4px); }
    50% { transform: translateY(-6px); }
}

@keyframes footerSlimePulse {
    0%, 100% { opacity: 0.5; }
    50% { opacity: 1; }
}

.animate-rise-up {
    opacity: 0;
    transform: translateY(26px);
    animation: riseUpIn 620ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
    will-change: transform, opacity;
}

.animate-delay-1 { animation-delay: 80ms; }
.animate-delay-2 { animation-delay: 170ms; }
.animate-delay-3 { animation-delay: 260ms; }
.animate-delay-4 { animation-delay: 320ms; }
.animate-delay-card-1 { animation-delay: 360ms; }
.animate-delay-card-2 { animation-delay: 420ms; }
.animate-delay-card-3 { animation-delay: 480ms; }
.animate-delay-card-4 { animation-delay: 540ms; }
.animate-delay-card-5 { animation-delay: 600ms; }
.animate-delay-card-6 { animation-delay: 660ms; }

@keyframes riseUpIn {
    0% {
        opacity: 0;
        transform: translateY(26px) scale(0.99);
    }
    65% {
        opacity: 1;
        transform: translateY(-3px) scale(1);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .animate-rise-up {
        opacity: 1;
        transform: none;
        animation: none;
    }
}

.feature-card {
    background: rgba(15, 18, 25, 0.65);
    backdrop-filter: blur(8px);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
    transition: transform 160ms ease, border-color 160ms ease, background 160ms ease;
}

.feature-card:hover {
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.12);
    background: rgba(20, 24, 33, 0.8);
}

.card-accent-emerald {
    border-left: 2px solid rgba(16, 185, 129, 0.35);
}

.card-accent-emerald:hover {
    border-left-color: rgba(16, 185, 129, 0.6);
}

.card-accent-amber {
    border-left: 2px solid rgba(251, 191, 36, 0.35);
}

.card-accent-amber:hover {
    border-left-color: rgba(251, 191, 36, 0.6);
}

.card-accent-indigo {
    border-left: 2px solid rgba(99, 102, 241, 0.35);
}

.card-accent-indigo:hover {
    border-left-color: rgba(99, 102, 241, 0.6);
}
</style>
