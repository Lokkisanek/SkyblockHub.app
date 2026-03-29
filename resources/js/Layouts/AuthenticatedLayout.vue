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
                <!-- Slime glow -->
                <div class="slime-glow-container">
                    <div class="slime-glow"></div>
                </div>

                <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-14 items-center justify-between">

                        <!-- Left: Logo -->
                        <div class="flex shrink-0 items-center">
                            <Link :href="route('dashboard')" class="flex items-center">
                                <span class="text-sm font-bold tracking-wide text-white">SKYBLOCKHUB</span>
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
                            <Link
                                v-if="currentMayor?.name"
                                :href="route('mayors')"
                                class="hidden text-[11px] leading-tight text-neutral transition hover:text-white lg:block"
                            >
                                <div class="font-semibold text-white">{{ currentMayor.name }}</div>
                                <div>{{ mayorPerkSummary }}</div>
                            </Link>

                            <!-- GitHub Star -->
                            <a
                                href="https://github.com/Lokkisanek/SkyblockHub.play"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-white/50 transition hover:text-white"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                                </svg>
                            </a>

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
                                    class="text-xs font-medium text-white transition hover:text-white/70"
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
    position: relative;
    z-index: 50;
    background: linear-gradient(180deg, rgba(16, 16, 16, 0.95) 0%, rgba(16, 16, 16, 0.88) 100%);
    backdrop-filter: blur(16px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

/* ── Slime glow (square shape) ── */
.slime-glow-container {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    overflow: hidden;
    pointer-events: none;
    z-index: 1;
}

.slime-glow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 120px;
    height: 50px;
    border-radius: 4px;
    background: rgba(93, 211, 93, 0.08);
    filter: blur(35px);
    pointer-events: none;
    animation: slimeGlowDrift 35s ease-in-out infinite, slimeGlowBounce 4s ease-in-out infinite;
}

.slime-glow::after {
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
    animation: slimeGlowPulse 6s ease-in-out infinite;
}

@keyframes slimeGlowDrift {
    0%, 100% { left: 8%; }
    33%      { left: 45%; }
    66%      { left: 75%; }
}

@keyframes slimeGlowBounce {
    0%, 100% { transform: translateY(4px); }
    50%      { transform: translateY(-6px); }
}

@keyframes slimeGlowPulse {
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
