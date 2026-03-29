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
        title: 'About the Project',
        subtitle: 'Open source & community',
        description: 'Learn about SkyblockHub, the developer behind it, and how you can support the project.',
        metric: 'Open source',
        routeName: 'about',
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

function metricColorClass(accent) {
    if (accent === 'emerald') return 'text-emerald-400/80';
    if (accent === 'amber') return 'text-amber-400/80';
    return 'text-indigo-400/80';
}
</script>

<template>
    <Head title="SkyblockHub" />

    <AuthenticatedLayout>
        <div class="pt-14 pb-20 sm:pt-16 sm:pb-24 lg:pt-20 lg:pb-28">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <section class="animate-rise-up animate-delay-1 mb-16">
                    <h1 class="mx-auto max-w-5xl text-center text-5xl font-black leading-[1.05] tracking-tight text-white sm:text-6xl lg:text-8xl">
                        Skyblock Intelligence. Simplified
                    </h1>

                    <p class="mx-auto mt-6 max-w-2xl text-center text-base leading-relaxed text-white/60 sm:text-lg lg:text-xl">
                        Real-time Bazaar tracking, profile analytics, and event countdowns. Everything you need to dominate the Hub, all in one place.
                    </p>
                </section>

                <section class="animate-rise-up animate-delay-2 mb-12">
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

                <section class="animate-rise-up animate-delay-3">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-white/45">What You Can Use Right Now</h2>
                        <div class="text-xs text-white/40">Interactive overview</div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-6">
                        <Link
                            v-for="(card, index) in featureCards"
                            :key="card.id"
                            :href="route(card.routeName)"
                            class="feature-card group flex flex-col justify-between rounded-xl border border-white/[0.06] p-5"
                            :class="[cardAccentClass(card.accent), cardLayoutClass(card.layout), `animate-rise-up animate-delay-card-${index + 1}`]"
                        >
                            <div>
                                <span class="mb-3 inline-block text-[10px] font-bold uppercase tracking-widest" :class="metricColorClass(card.accent)">
                                    {{ card.metric }}
                                </span>

                                <h3 class="text-[15px] font-semibold text-white">{{ card.title }}</h3>
                                <p class="mt-2 text-[13px] leading-relaxed text-white/50">{{ card.description }}</p>
                            </div>

                            <div class="mt-5 flex items-center gap-1 text-xs font-medium text-white/40 transition group-hover:text-white/70">
                                Explore
                                <svg class="h-3 w-3 transition-transform group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
                            </div>
                        </Link>
                    </div>
                </section>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer-wrapper relative">
            <div class="slime-glow-footer-container">
                <div class="slime-glow-footer"></div>
            </div>

            <div class="relative z-10 mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 gap-8 sm:grid-cols-4">

                    <div class="col-span-2 sm:col-span-1">
                        <Link :href="route('dashboard')" class="text-sm font-bold tracking-wide text-white">SKYBLOCKHUB</Link>
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
/* ── Footer ── */
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
    33%      { left: 40%; }
    66%      { left: 10%; }
}

@keyframes footerSlimeBounce {
    0%, 100% { transform: translateY(4px); }
    50%      { transform: translateY(-6px); }
}

@keyframes footerSlimePulse {
    0%, 100% { opacity: 0.5; }
    50%      { opacity: 1; }
}
.animate-rise-up {
    opacity: 0;
    transform: translateY(26px);
    animation: riseUpIn 620ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
    will-change: transform, opacity;
}

.animate-delay-1 {
    animation-delay: 80ms;
}

.animate-delay-2 {
    animation-delay: 170ms;
}

.animate-delay-3 {
    animation-delay: 260ms;
}

.animate-delay-card-1 {
    animation-delay: 320ms;
}

.animate-delay-card-2 {
    animation-delay: 380ms;
}

.animate-delay-card-3 {
    animation-delay: 440ms;
}

.animate-delay-card-4 {
    animation-delay: 500ms;
}

.animate-delay-card-5 {
    animation-delay: 560ms;
}

.animate-delay-card-6 {
    animation-delay: 620ms;
}

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
