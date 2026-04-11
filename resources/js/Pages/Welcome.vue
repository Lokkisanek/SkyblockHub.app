<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const { t } = useI18n();

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    socialProofMetrics: { type: Object, default: () => ({}) },
    canRegister: { type: Boolean, default: false },
    laravelVersion: { type: String, required: false },
    phpVersion: { type: String, required: false },
});

const page = usePage();
const isTestingAdmin = computed(() => Boolean(page.props.auth?.testing_admin));

const searchUsername = ref('');
const siteOrigin = typeof window !== 'undefined' ? window.location.origin : 'http://localhost:8000';
const canonicalUrl = `${siteOrigin}/`;
const pageTitle = 'Hypixel SkyBlock Tools - Bazaar Flips, NPC Arbitrage, Profiles';
const pageDescription = 'SkyblockHub is a clean Hypixel SkyBlock dashboard for Bazaar flips, NPC arbitrage, profile analysis, mayor perks, and event timing.';

const featureCards = computed(() => [
    {
        id: 'bazaar',
        title: t('welcome.features.bazaarTitle'),
        subtitle: t('welcome.features.bazaarSub'),
        description: t('welcome.features.bazaarDesc'),
        icon: '📊',
        routeName: 'bazaar',
        accent: 'emerald',
    },
    {
        id: 'npc',
        title: t('welcome.features.npcTitle'),
        subtitle: t('welcome.features.npcSub'),
        description: t('welcome.features.npcDesc'),
        icon: '🪙',
        routeName: 'npc-flips',
        accent: 'amber',
    },
    {
        id: 'profiles',
        title: t('welcome.features.profileTitle'),
        subtitle: t('welcome.features.profileSub'),
        description: t('welcome.features.profileDesc'),
        icon: '🔍',
        routeName: 'profile-stats',
        accent: 'emerald',
    },
    {
        id: 'events',
        title: t('welcome.features.eventTitle'),
        subtitle: t('welcome.features.eventSub'),
        description: t('welcome.features.eventDesc'),
        icon: '⏱️',
        routeName: 'event-timer',
        accent: 'indigo',
    },
    {
        id: 'mayors',
        title: t('welcome.features.mayorTitle'),
        subtitle: t('welcome.features.mayorSub'),
        description: t('welcome.features.mayorDesc'),
        icon: '🏛️',
        routeName: 'mayors',
        accent: 'indigo',
    },
]);

const socialMetrics = ref({
    registered_players: Number(props.socialProofMetrics?.registered_players || 0),
    tracked_flips: Number(props.socialProofMetrics?.tracked_flips || 0),
    profiles_loaded: Number(props.socialProofMetrics?.profiles_loaded || 0),
});

let socialMetricsInterval = null;

function formatMetric(value) {
    const n = Number(value || 0);

    if (n >= 1000000) {
        const v = (n / 1000000).toFixed(1).replace('.0', '');

        return `${v}M+`;
    }

    if (n >= 1000) {
        const v = (n / 1000).toFixed(1).replace('.0', '');

        return `${v}K+`;
    }

    return `${n.toLocaleString('en-US')}+`;
}

async function refreshSocialMetrics() {
    try {
        const response = await fetch('/api/social-proof-metrics', {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            return;
        }

        const payload = await response.json();

        if (!payload?.metrics) {
            return;
        }

        socialMetrics.value = {
            registered_players: Number(payload.metrics.registered_players || 0),
            tracked_flips: Number(payload.metrics.tracked_flips || 0),
            profiles_loaded: Number(payload.metrics.profiles_loaded || 0),
        };
    } catch (_) {
        // Keep last known values when refresh fails.
    }
}

onMounted(() => {
    refreshSocialMetrics();
    socialMetricsInterval = window.setInterval(refreshSocialMetrics, 60000);
    window.addEventListener('keydown', onGlobalKeydown);
});

onBeforeUnmount(() => {
    if (socialMetricsInterval !== null) {
        window.clearInterval(socialMetricsInterval);
    }

    window.removeEventListener('keydown', onGlobalKeydown);
});

const socialStats = computed(() => [
    {
        value: formatMetric(socialMetrics.value.registered_players),
        label: t('welcome.social.stats.playersLabel'),
    },
    {
        value: formatMetric(socialMetrics.value.tracked_flips),
        label: t('welcome.social.stats.flipsLabel'),
    },
    {
        value: formatMetric(socialMetrics.value.profiles_loaded),
        label: t('welcome.social.stats.profilesLabel'),
    },
]);

const activeScreenshot = ref(null);

function openScreenshot(item) {
    activeScreenshot.value = {
        title: item.title,
        image: item.image,
    };
}

function closeScreenshot() {
    activeScreenshot.value = null;
}

function onGlobalKeydown(event) {
    if (event.key === 'Escape') {
        closeScreenshot();
    }
}

const socialUseCases = computed(() => [
    {
        title: t('welcome.social.useCases.dashboardTitle'),
        description: t('welcome.social.useCases.dashboardDesc'),
        image: '/img/social-proof/bazaar-usecase.webp',
        badge: t('welcome.social.betaTag'),
    },
    {
        title: t('welcome.social.useCases.bazaarTitle'),
        description: t('welcome.social.useCases.bazaarDesc'),
        image: '/img/social-proof/dashboard-usecase.webp',
    },
    {
        title: t('welcome.social.useCases.profileTitle'),
        description: t('welcome.social.useCases.profileDesc'),
        image: '/img/social-proof/profile-usecase.webp',
    },
]);

const socialTestimonials = computed(() => [
    {
        quote: t('welcome.social.testimonials.quote1'),
        author: t('welcome.social.testimonials.author1'),
        role: t('welcome.social.testimonials.role1'),
    },
    {
        quote: t('welcome.social.testimonials.quote2'),
        author: t('welcome.social.testimonials.author2'),
        role: t('welcome.social.testimonials.role2'),
    },
    {
        quote: t('welcome.social.testimonials.quote3'),
        author: t('welcome.social.testimonials.author3'),
        role: t('welcome.social.testimonials.role3'),
    },
]);

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
                        {{ $t('welcome.hero') }}
                    </h1>

                    <p class="mx-auto mt-6 max-w-3xl text-center text-lg leading-relaxed text-white/80 sm:text-xl lg:text-2xl">
                        {{ $t('welcome.subtitle') }}
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
                                    :placeholder="$t('welcome.searchPlaceholder')"
                                    class="w-full rounded-xl border border-border/80 bg-surface-800/80 py-3 pl-14 pr-4 text-base text-white placeholder:text-neutral/80 transition focus:border-profit/70 focus:outline-none focus:ring-2 focus:ring-profit/25"
                                    @keyup.enter="submitSearch"
                                />
                            </div>
                            <button
                                @click="submitSearch"
                                class="inline-flex h-[46px] items-center justify-center rounded-xl border border-profit/35 bg-profit/20 px-6 text-base font-semibold text-profit transition hover:bg-profit/30 hover:text-white"
                            >
                                {{ $t('welcome.openProfile') }}
                            </button>
                        </div>
                    </div>
                    <p class="mt-3 text-center text-sm text-white/65">{{ $t('welcome.searchHelper') }}</p>
                </section>

                <section class="animate-rise-up animate-delay-3 mb-12">
                    <div class="mx-auto max-w-5xl">
                        <!-- Discord Login CTA (guests) -->
                        <div v-if="canLogin" class="mb-10 rounded-2xl border border-[#5865F2]/25 bg-[#5865F2]/[0.06] p-8 text-center shadow-[0_16px_48px_rgba(88,101,242,0.08)] backdrop-blur-sm sm:p-10">
                            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl border border-[#5865F2]/30 bg-[#5865F2]/15">
                                <svg class="h-8 w-8 text-[#5865F2]" viewBox="0 0 24 24" fill="currentColor"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
                            </div>
                            <h2 class="text-2xl font-bold text-white sm:text-3xl">{{ $t('welcome.getStarted') }}</h2>
                            <p class="mx-auto mt-3 max-w-lg text-base text-white/60">{{ $t('welcome.connectCta') }}</p>
                            <a
                                :href="route('auth.discord')"
                                class="mt-6 inline-flex items-center justify-center gap-3 rounded-xl border border-[#5865F2]/50 bg-[#5865F2] px-8 py-3.5 text-base font-bold text-white shadow-[0_4px_20px_rgba(88,101,242,0.35)] transition hover:bg-[#4752C4] hover:shadow-[0_4px_24px_rgba(88,101,242,0.5)]"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
                                {{ $t('welcome.loginDiscord') }}
                            </a>
                        </div>
                        <!-- Logged-in CTA -->
                        <div v-else class="mb-10 text-center">
                            <Link
                                :href="route('dashboard')"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-profit/40 bg-profit/15 px-8 py-4 text-lg font-semibold text-profit transition hover:bg-profit/30 hover:text-white"
                            >
                                {{ $t('welcome.openDashboard') }}
                            </Link>
                        </div>

                        <!-- Pricing Cards -->
                        <div class="grid gap-6 md:grid-cols-2">
                            <!-- VIP -->
                            <div class="rounded-2xl border border-white/10 bg-surface-900/75 p-6 shadow-[0_16px_40px_rgba(0,0,0,0.25)] backdrop-blur-sm">
                                <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-emerald-400/80">{{ $t('welcome.pricing.vipTitle') }}</p>
                                <p class="mt-3 text-4xl font-black text-white">{{ $t('welcome.pricing.vipPrice') }}<span class="text-lg font-medium text-white/45">{{ $t('welcome.pricing.vipPeriod') }}</span></p>
                                <ul class="mt-5 space-y-2.5 text-sm leading-relaxed text-white/75">
                                    <li class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-emerald-400/70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                        {{ $t('welcome.pricing.vipFeature1') }}
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-emerald-400/70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                        {{ $t('welcome.pricing.vipFeature2') }}
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-emerald-400/70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                        {{ $t('welcome.pricing.vipFeature3') }}
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-emerald-400/70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                        {{ $t('welcome.pricing.vipFeature4') }}
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-emerald-400/70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                        {{ $t('welcome.pricing.vipFeature5') }}
                                    </li>
                                </ul>
                                <Link
                                    :href="route('billing')"
                                    class="mt-6 flex w-full items-center justify-center rounded-xl border border-emerald-400/30 bg-emerald-500/10 py-3 text-sm font-semibold text-emerald-400 transition hover:bg-emerald-500/20 hover:text-emerald-300"
                                >
                                    {{ $t('welcome.pricing.subscribeVip') }}
                                </Link>
                            </div>

                            <!-- MVP (highlighted) -->
                            <div class="relative rounded-2xl border border-amber-400/25 bg-surface-900/75 p-6 shadow-[0_16px_40px_rgba(0,0,0,0.25),0_0_60px_rgba(251,191,36,0.06)] backdrop-blur-sm">
                                <span class="absolute -top-3 right-5 rounded-full border border-amber-400/40 bg-amber-500/15 px-3 py-1 text-[10px] font-bold uppercase tracking-widest text-amber-400">{{ $t('welcome.pricing.freeTrial') }}</span>
                                <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-amber-400/80">{{ $t('welcome.pricing.mvpTitle') }}</p>
                                <p class="mt-3 text-4xl font-black text-white">{{ $t('welcome.pricing.mvpPrice') }}<span class="text-lg font-medium text-white/45">{{ $t('welcome.pricing.mvpPeriod') }}</span></p>
                                <ul class="mt-5 space-y-2.5 text-sm leading-relaxed text-white/75">
                                    <li class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-amber-400/70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                        {{ $t('welcome.pricing.mvpFeature1') }}
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-amber-400/70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                        {{ $t('welcome.pricing.mvpFeature2') }}
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-amber-400/70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                        {{ $t('welcome.pricing.mvpFeature3') }}
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-amber-400/70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                        {{ $t('welcome.pricing.mvpFeature4') }}
                                    </li>
                                </ul>
                                <Link
                                    :href="route('billing')"
                                    class="mt-6 flex w-full items-center justify-center rounded-xl border border-amber-400/40 bg-amber-500/15 py-3 text-sm font-bold text-amber-400 transition hover:bg-amber-500/25 hover:text-amber-300"
                                >
                                    {{ $t('welcome.pricing.startTrial') }}
                                </Link>
                                <Link
                                    :href="route('billing')"
                                    class="mt-2 flex w-full items-center justify-center rounded-xl border border-white/10 bg-white/5 py-3 text-sm font-semibold text-white/70 transition hover:bg-white/10 hover:text-white"
                                >
                                    {{ $t('welcome.pricing.subscribeMvp') }}
                                </Link>
                            </div>
                        </div>

                        <p class="mt-5 text-center text-xs text-white/30">
                            <Link :href="route('pricing')" class="underline decoration-white/20 underline-offset-2 transition hover:text-white/60">{{ $t('welcome.pricing.comparePlans') }}</Link>
                        </p>
                    </div>
                </section>

                <section class="animate-rise-up animate-delay-4 mb-12">
                    <div class="mb-6 text-center">
                        <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-emerald-300/80">{{ $t('welcome.social.kicker') }}</p>
                        <h2 class="mt-2 text-2xl font-black text-white sm:text-3xl">{{ $t('welcome.social.title') }}</h2>
                        <p class="mx-auto mt-2 max-w-2xl text-sm text-white/55">{{ $t('welcome.social.subtitle') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <article v-for="item in socialStats" :key="item.label" class="social-stat-card rounded-2xl border border-white/[0.07] p-5">
                            <p class="text-3xl font-black text-white">{{ item.value }}</p>
                            <p class="mt-2 text-xs font-semibold uppercase tracking-[0.16em] text-white/55">{{ item.label }}</p>
                        </article>
                    </div>

                    <div class="mt-6 grid gap-4 lg:grid-cols-3">
                        <article v-for="item in socialUseCases" :key="item.title" class="social-proof-card rounded-2xl border border-white/[0.07] p-4">
                            <button class="social-proof-trigger" type="button" @click="openScreenshot(item)">
                                <img :src="item.image" :alt="item.title" class="social-proof-image" loading="lazy" decoding="async" />
                                <span v-if="item.badge" class="social-proof-badge">{{ item.badge }}</span>
                            </button>
                            <h3 class="mt-4 text-base font-bold text-white">{{ item.title }}</h3>
                            <p class="mt-1 text-sm text-white/55">{{ item.description }}</p>
                        </article>
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-3">
                        <article v-for="item in socialTestimonials" :key="item.author" class="social-quote-card rounded-2xl border border-white/[0.07] p-5">
                            <p class="text-sm leading-relaxed text-white/75">"{{ item.quote }}"</p>
                            <p class="mt-4 text-sm font-semibold text-white">{{ item.author }}</p>
                            <p class="text-xs uppercase tracking-[0.15em] text-white/35">{{ item.role }}</p>
                        </article>
                    </div>

                    <div v-if="activeScreenshot" class="social-lightbox" @click.self="closeScreenshot">
                        <button class="social-lightbox-close" type="button" @click="closeScreenshot">Close</button>
                        <img :src="activeScreenshot.image" :alt="activeScreenshot.title" class="social-lightbox-image" />
                        <p class="mt-3 text-sm text-white/80">{{ activeScreenshot.title }}</p>
                    </div>
                </section>

                <section class="animate-rise-up animate-delay-5">
                    <div class="mb-6 text-center">
                        <h2 class="text-xl font-bold text-white sm:text-2xl">{{ $t('welcome.freeTitle') }}</h2>
                        <p class="mt-2 text-sm text-white/50">{{ $t('welcome.coreModules') }}</p>
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
                                {{ $t('welcome.openModule') }}
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
                <div class="grid grid-cols-2 gap-8 sm:grid-cols-5">
                    <div class="col-span-2 sm:col-span-1">
                        <Link :href="route('dashboard')" class="flex items-center gap-2 text-sm font-bold tracking-wide text-white">
                            <ApplicationLogo tone="light" class="h-7 w-7 shrink-0" />
                            <span>SkyblockHub</span>
                        </Link>
                        <p class="mt-2 text-xs leading-relaxed text-white/30">{{ $t('welcome.footer.tagline') }}</p>
                    </div>

                    <div>
                        <h3 class="text-[10px] font-bold uppercase tracking-widest text-white/40">{{ $t('welcome.footer.modules') }}</h3>
                        <ul class="mt-3 space-y-2">
                            <li><Link :href="route('bazaar')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.bazaarFlips') }}</Link></li>
                            <li><Link :href="route('npc-flips')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.npcArbitrage') }}</Link></li>
                            <li><Link :href="route('event-timer')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.eventTimer') }}</Link></li>
                            <li><Link :href="route('mayors')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.mayorIntel') }}</Link></li>
                            <li><Link :href="route('profile-stats')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.profileStats') }}</Link></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-[10px] font-bold uppercase tracking-widest text-white/40">{{ $t('welcome.footer.comingSoon') }}</h3>
                        <ul class="mt-3 space-y-2">
                            <li>
                                <Link v-if="isTestingAdmin" :href="route('crafting')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.craftingArbitrage') }}</Link>
                                <span v-else class="text-xs text-white/20">{{ $t('welcome.footer.craftingArbitrage') }}</span>
                            </li>
                            <li>
                                <Link v-if="isTestingAdmin" :href="route('bin-sniper')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.binSniper') }}</Link>
                                <span v-else class="text-xs text-white/20">{{ $t('welcome.footer.binSniper') }}</span>
                            </li>
                            <li>
                                <Link v-if="isTestingAdmin" :href="route('portfolio')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.portfolioTracker') }}</Link>
                                <span v-else class="text-xs text-white/20">{{ $t('welcome.footer.portfolioTracker') }}</span>
                            </li>
                            <li>
                                <Link v-if="isTestingAdmin" :href="route('dungeon-party')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.dungeonPartyFinder') }}</Link>
                                <span v-else class="text-xs text-white/20">{{ $t('welcome.footer.dungeonPartyFinder') }}</span>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-[10px] font-bold uppercase tracking-widest text-white/40">{{ $t('welcome.footer.project') }}</h3>
                        <ul class="mt-3 space-y-2">
                            <li><Link :href="route('about')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.about') }}</Link></li>
                            <li><a href="https://github.com/Lokkisanek/SkyblockHub.play" target="_blank" rel="noopener noreferrer" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.github') }}</a></li>
                            <li><a href="https://www.patreon.com/SkyblockHub" target="_blank" rel="noopener noreferrer" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.patreon') }}</a></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-[10px] font-bold uppercase tracking-widest text-white/40">{{ $t('welcome.footer.legal') }}</h3>
                        <ul class="mt-3 space-y-2">
                            <li><Link :href="route('pricing')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.pricingFaq') }}</Link></li>
                            <li><Link :href="route('privacy')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.privacyPolicy') }}</Link></li>
                            <li><Link :href="route('terms')" class="text-xs text-white/35 transition hover:text-white">{{ $t('welcome.footer.terms') }}</Link></li>
                        </ul>
                    </div>
                </div>

                <div class="mt-8 flex flex-col items-center justify-between gap-3 border-t border-white/[0.06] pt-6 sm:flex-row">
                    <p class="text-[11px] text-white/25">{{ $t('welcome.footer.copyright', { year: new Date().getFullYear() }) }}</p>
                    <p class="text-[11px] text-white/20">{{ $t('welcome.footer.notAffiliated') }}</p>
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
.animate-delay-5 { animation-delay: 380ms; }
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

.social-stat-card,
.social-proof-card,
.social-quote-card {
    background: rgba(15, 18, 25, 0.62);
    backdrop-filter: blur(8px);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
}

.social-proof-image {
    width: 100%;
    aspect-ratio: 16 / 9;
    object-fit: cover;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.social-proof-trigger {
    position: relative;
    display: block;
    width: 100%;
    border: 0;
    padding: 0;
    background: transparent;
    text-align: left;
    cursor: zoom-in;
}

.social-proof-trigger:hover .social-proof-image {
    border-color: rgba(255, 255, 255, 0.35);
}

.social-proof-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    border-radius: 999px;
    border: 1px solid rgba(52, 211, 153, 0.55);
    background: rgba(16, 185, 129, 0.2);
    padding: 3px 8px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #a7f3d0;
}

.social-lightbox {
    position: fixed;
    inset: 0;
    z-index: 80;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(5, 8, 13, 0.9);
    backdrop-filter: blur(8px);
}

.social-lightbox-image {
    width: min(1120px, 95vw);
    max-height: 78vh;
    object-fit: contain;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.social-lightbox-close {
    position: absolute;
    top: 20px;
    right: 20px;
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.08);
    padding: 6px 10px;
    font-size: 12px;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.85);
}
</style>
