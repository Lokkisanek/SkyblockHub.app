<script setup>
import { computed, ref } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, usePage, router } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const page = usePage();

const user = computed(() => page.props.auth?.user ?? null);
const currentMayor = computed(() => page.props.currentMayor ?? null);
const isTestingAdmin = computed(() => Boolean(page.props.auth?.testing_admin));
const mayorPerkSummary = computed(() => {
    const count = Number(currentMayor.value?.active_perk_count ?? 0);
    return count === 1 ? '1 active perk' : `${count} active perks`;
});

const currentLocale = ref(document.documentElement.lang || 'en');

function switchLocale(locale) {
    currentLocale.value = locale;
}

const displayName = computed(() => {
    if (!user.value) return '';
    return user.value.minecraft_username || user.value.discord_username || user.value.name || 'Profile';
});

function isActive(routeName) {
    return route().current(routeName);
}

function isBazaarActive() {
    return route().current('bazaar') || route().current('npc-flips') || (isTestingAdmin.value && route().current('crafting'));
}

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <div>
        <div class="min-h-screen bg-surface-900" style="background-image: url('/background.webp'); background-size: cover; background-position: center; background-attachment: fixed;">
            <nav class="nav-wrapper relative">
                <!-- Animated vertical glow sweep -->
                <div class="nav-glow-sweep"></div>

                <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-14 items-center justify-between">

                        <!-- Left: Logo -->
                        <div class="flex shrink-0 items-center">
                            <Link :href="route('dashboard')" class="flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-purple-500 to-indigo-600">
                                    <svg class="h-4 w-4 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                                </div>
                                <span class="hidden text-sm font-bold tracking-wide text-white sm:inline">SKYBLOCKHUB</span>
                            </Link>
                        </div>

                        <!-- Center: Navigation -->
                        <div class="hidden items-center gap-1 md:flex">
                                <Link
                                    :href="route('dashboard')"
                                    class="nav-link"
                                    :class="{ active: isActive('dashboard') }"
                                >
                                    Dashboard
                                </Link>

                                <Dropdown align="left" width="48">
                                    <template #trigger>
                                        <button
                                            class="nav-link inline-flex items-center gap-1"
                                            :class="{ active: isBazaarActive() }"
                                        >
                                            Bazaar
                                            <svg class="h-3 w-3 opacity-50" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </template>
                                    <template #content>
                                        <DropdownLink :href="route('bazaar')">Flips</DropdownLink>
                                        <DropdownLink :href="route('npc-flips')">NPC Flips</DropdownLink>
                                        <DropdownLink v-if="isTestingAdmin" :href="route('crafting')">Crafting Flips</DropdownLink>
                                    </template>
                                </Dropdown>

                                <Link
                                    :href="route('profile-stats')"
                                    class="nav-link"
                                    :class="{ active: isActive('profile-stats') }"
                                >
                                    Profile Stats
                                </Link>

                                <Link
                                    :href="route('event-timer')"
                                    class="nav-link"
                                    :class="{ active: isActive('event-timer') }"
                                >
                                    Event Timer
                                </Link>

                                <template v-if="isTestingAdmin">
                                    <Link :href="route('dungeon-party')" class="nav-link" :class="{ active: isActive('dungeon-party') }">
                                        Party Finder
                                    </Link>
                                    <Link :href="route('portfolio')" class="nav-link" :class="{ active: isActive('portfolio') }">
                                        Portfolio
                                    </Link>
                                    <Link :href="route('bin-sniper')" class="nav-link" :class="{ active: isActive('bin-sniper') }">
                                        BIN Sniper
                                    </Link>
                                </template>
                            </div>

                        <!-- Right: Mayor + Lang + Auth -->
                        <div class="hidden items-center gap-4 md:flex">
                            <!-- Mayor info -->
                            <div
                                v-if="currentMayor?.name"
                                class="hidden rounded-lg border border-white/10 bg-white/5 px-2.5 py-1.5 text-[11px] leading-tight text-neutral lg:block"
                            >
                                <div class="font-semibold text-white">{{ currentMayor.name }}</div>
                                <div>{{ mayorPerkSummary }}</div>
                            </div>

                            <!-- Language Switcher -->
                            <div class="flex items-center gap-1 text-xs">
                                <button
                                    @click="switchLocale('en')"
                                    class="rounded px-1.5 py-0.5 transition"
                                    :class="currentLocale === 'en' ? 'bg-white/10 text-white' : 'text-neutral hover:text-white'"
                                >
                                    EN
                                </button>
                                <span class="text-white/20">|</span>
                                <button
                                    @click="switchLocale('cz')"
                                    class="rounded px-1.5 py-0.5 transition"
                                    :class="currentLocale === 'cz' ? 'bg-white/10 text-white' : 'text-neutral hover:text-white'"
                                >
                                    CZ
                                </button>
                            </div>

                            <!-- Auth: Login/Register or Profile -->
                            <template v-if="user">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button class="flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-white/10">
                                            <div class="flex h-5 w-5 items-center justify-center rounded-full bg-gradient-to-br from-purple-400 to-indigo-500 text-[10px] font-bold uppercase text-white">
                                                {{ displayName.charAt(0) }}
                                            </div>
                                            {{ displayName }}
                                        </button>
                                    </template>
                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')">Settings</DropdownLink>
                                        <button
                                            class="block w-full px-4 py-2 text-start text-xs leading-5 text-neutral hover:bg-surface-500 hover:text-white focus:bg-surface-500 focus:outline-none"
                                            @click="logout"
                                        >
                                            Log Out
                                        </button>
                                    </template>
                                </Dropdown>
                            </template>
                            <template v-else>
                                <Link
                                    :href="route('login')"
                                    class="rounded-full border border-purple-500/50 bg-gradient-to-r from-purple-600/80 to-indigo-600/80 px-4 py-1.5 text-xs font-semibold text-white shadow-lg shadow-purple-500/20 transition hover:shadow-purple-500/40"
                                >
                                    Login
                                </Link>
                            </template>
                        </div>

                        <!-- Hamburger (mobile) -->
                        <div class="flex items-center md:hidden">
                            <button
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex items-center justify-center rounded-lg p-2 text-neutral transition hover:bg-white/10 hover:text-white focus:outline-none"
                            >
                                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                    <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mobile Navigation Menu -->
                <div
                    :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }"
                    class="relative z-10 border-t border-white/10 md:hidden"
                >
                    <div class="space-y-1 px-4 pb-4 pt-3">
                        <Link :href="route('dashboard')" class="mobile-link" :class="{ 'mobile-link-active': isActive('dashboard') }">
                            Dashboard
                        </Link>
                        <div class="px-2 pb-1 pt-3 text-[10px] font-semibold uppercase tracking-widest text-neutral">Bazaar</div>
                        <Link :href="route('bazaar')" class="mobile-link ps-6" :class="{ 'mobile-link-active': isActive('bazaar') }">
                            Flips
                        </Link>
                        <Link :href="route('npc-flips')" class="mobile-link ps-6" :class="{ 'mobile-link-active': isActive('npc-flips') }">
                            NPC Flips
                        </Link>
                        <Link v-if="isTestingAdmin" :href="route('crafting')" class="mobile-link ps-6" :class="{ 'mobile-link-active': isActive('crafting') }">
                            Crafting Flips
                        </Link>
                        <Link :href="route('profile-stats')" class="mobile-link" :class="{ 'mobile-link-active': isActive('profile-stats') }">
                            Profile Stats
                        </Link>
                        <Link :href="route('event-timer')" class="mobile-link" :class="{ 'mobile-link-active': isActive('event-timer') }">
                            Event Timer
                        </Link>
                        <template v-if="isTestingAdmin">
                            <Link :href="route('dungeon-party')" class="mobile-link" :class="{ 'mobile-link-active': isActive('dungeon-party') }">Party Finder</Link>
                            <Link :href="route('portfolio')" class="mobile-link" :class="{ 'mobile-link-active': isActive('portfolio') }">Portfolio</Link>
                            <Link :href="route('bin-sniper')" class="mobile-link" :class="{ 'mobile-link-active': isActive('bin-sniper') }">BIN Sniper</Link>
                        </template>

                        <!-- Mobile lang + auth -->
                        <div class="mt-3 flex items-center gap-3 border-t border-white/10 pt-3">
                            <div class="flex items-center gap-1 text-xs">
                                <button @click="switchLocale('en')" class="rounded px-1.5 py-0.5" :class="currentLocale === 'en' ? 'bg-white/10 text-white' : 'text-neutral'">EN</button>
                                <span class="text-white/20">|</span>
                                <button @click="switchLocale('cz')" class="rounded px-1.5 py-0.5" :class="currentLocale === 'cz' ? 'bg-white/10 text-white' : 'text-neutral'">CZ</button>
                            </div>
                            <template v-if="user">
                                <span class="text-xs text-white">{{ displayName }}</span>
                                <button @click="logout" class="text-xs text-neutral hover:text-white">Log Out</button>
                            </template>
                            <template v-else>
                                <Link :href="route('login')" class="text-xs font-medium text-purple-400 hover:text-white">Login</Link>
                            </template>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
/* ── Nav wrapper ── */
.nav-wrapper {
    background: linear-gradient(180deg, rgba(16, 16, 16, 0.95) 0%, rgba(16, 16, 16, 0.88) 100%);
    backdrop-filter: blur(16px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

/* ── Vertical glow sweep ── */
.nav-glow-sweep {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 220px;
    background: radial-gradient(
        ellipse 100% 100% at center,
        rgba(139, 92, 246, 0.15) 0%,
        rgba(120, 70, 220, 0.08) 40%,
        transparent 100%
    );
    filter: blur(25px);
    animation: sweepDrift 12s ease-in-out infinite, sweepPulse 5s ease-in-out infinite;
    z-index: 1;
    pointer-events: none;
}

.nav-glow-sweep::after {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 120px;
    width: 180px;
    background: radial-gradient(
        ellipse 100% 100% at center,
        rgba(59, 130, 246, 0.12) 0%,
        rgba(56, 100, 220, 0.06) 40%,
        transparent 100%
    );
    filter: blur(25px);
    animation: sweepPulse 5s ease-in-out infinite reverse;
}

@keyframes sweepDrift {
    0%, 100% { left: 15%; }
    50%      { left: 65%; }
}

@keyframes sweepPulse {
    0%, 100% { opacity: 0.5; }
    50%      { opacity: 1; }
}

/* ── Desktop nav links ── */
.nav-link {
    position: relative;
    padding: 6px 12px;
    font-size: 13px;
    font-weight: 500;
    color: #aaa;
    border-radius: 6px;
    transition: color 0.2s, background 0.2s;
    text-decoration: none;
    white-space: nowrap;
}

.nav-link:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.06);
}

.nav-link.active {
    color: #fff;
}

/* ── Mobile nav links ── */
.mobile-link {
    display: block;
    padding: 8px 12px;
    font-size: 13px;
    font-weight: 500;
    color: #aaa;
    border-radius: 6px;
    transition: color 0.15s, background 0.15s;
}

.mobile-link:hover,
.mobile-link-active {
    color: #fff;
    background: rgba(255, 255, 255, 0.06);
}
</style>
