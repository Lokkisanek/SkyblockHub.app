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
        subtitle: 'Live spread and margin',
        description: 'Find practical opportunities quickly on live market data.',
        metric: 'Live market deltas',
        routeName: 'bazaar',
        accent: 'emerald',
        layout: 'main',
    },
    {
        id: 'npc',
        title: 'NPC Arbitrage',
        subtitle: 'Fast price comparison',
        description: 'Compare Bazaar buys vs NPC sells in one table.',
        metric: 'Low-friction route',
        routeName: 'npc-flips',
        accent: 'amber',
        layout: 'twin',
    },
    {
        id: 'profiles',
        title: 'Profile Stats',
        subtitle: 'Complete profile view',
        description: 'Inspect gear, skills, pets, dungeons, and collections.',
        metric: 'Deep account visibility',
        routeName: 'profile-stats',
        accent: 'emerald',
        layout: 'twin',
    },
    {
        id: 'events',
        title: 'Event Timer',
        subtitle: 'Cycle-aware planning',
        description: 'Track key events with clear countdowns.',
        metric: 'Always on schedule',
        routeName: 'event-timer',
        accent: 'indigo',
        layout: 'sidebar',
    },
    {
        id: 'mayors',
        title: 'Mayors',
        subtitle: 'Perks and cycle context',
        description: 'See active perks and upcoming election context.',
        metric: 'Meta-ready decisions',
        routeName: 'mayors',
        accent: 'indigo',
        layout: 'sidebar',
    },
    {
        id: 'about',
        title: 'About Project',
        subtitle: 'Open source',
        description: 'See project details and support options.',
        metric: 'Community driven',
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
    if (layout === 'main') return 'md:col-span-3';
    if (layout === 'sidebar') return 'md:col-span-2';
    return 'md:col-span-2';
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
                        One clean dashboard for flips, profiles, mayor perks, and event timing.
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
                    <div class="mx-auto max-w-5xl rounded-3xl border border-white/10 bg-surface-900/75 p-6 shadow-[0_24px_60px_rgba(0,0,0,0.28)] backdrop-blur-md sm:p-7">
                        <div class="grid gap-6 md:grid-cols-[1.3fr_1fr] md:items-start">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-white/55">Login</p>
                                <h2 class="mt-2 text-3xl font-semibold leading-tight text-white sm:text-4xl">Log in to unlock full experience</h2>
                                <p class="mt-3 text-base leading-relaxed text-white/75 sm:text-lg">Connect with Discord and keep all your tools in one flow.</p>

                                <a
                                    v-if="canLogin"
                                    :href="route('auth.discord')"
                                    class="mt-5 inline-flex items-center justify-center rounded-xl border border-profit/40 bg-profit/15 px-6 py-3 text-base font-semibold text-profit transition hover:bg-profit/30 hover:text-white"
                                >
                                    Continue with Discord
                                </a>
                                <Link
                                    v-else
                                    :href="route('dashboard')"
                                    class="mt-5 inline-flex items-center justify-center rounded-xl border border-white/15 bg-white/5 px-6 py-3 text-base font-semibold text-white transition hover:bg-white/10"
                                >
                                    Open Dashboard
                                </Link>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 sm:p-5">
                                <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-white/55">Benefits (you define later)</p>
                                <ul class="mt-3 space-y-2 text-sm leading-relaxed text-white/90 sm:text-base">
                                    <li>- Benefit #1 (placeholder)</li>
                                    <li>- Benefit #2 (placeholder)</li>
                                    <li>- Benefit #3 (placeholder)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="animate-rise-up animate-delay-4">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-base font-semibold uppercase tracking-[0.18em] text-white/60">What You Can Use Right Now</h2>
                        <div class="text-sm text-white/50">Modules</div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
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

                                <h3 class="text-[17px] font-semibold text-white">{{ card.title }}</h3>
                                <p class="mt-1 text-[11px] font-medium uppercase tracking-[0.18em] text-white/45">{{ card.subtitle }}</p>
                                <p class="mt-2 text-[14px] leading-relaxed text-white/70">{{ card.description }}</p>
                            </div>

                            <div class="mt-5 flex items-center gap-1 text-xs font-medium text-white/50 transition group-hover:text-white/80">
                                Explore
                                <svg class="h-3 w-3 transition-transform group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
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
