<script setup>
import { computed, ref } from 'vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const page = usePage();

const currentMayor = computed(() => page.props.currentMayor ?? null);
const isTestingAdmin = computed(() => Boolean(page.props.auth?.testing_admin));
const mayorPerkSummary = computed(() => {
    const count = Number(currentMayor.value?.active_perk_count ?? 0);
    return count === 1 ? '1 active perk' : `${count} active perks`;
});
</script>

<template>
    <div>
        <div class="min-h-screen bg-surface-900">
            <nav class="border-b border-border bg-surface-800">
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-12 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')" class="text-sm font-bold tracking-wide text-white uppercase">
                                    SkyblockHub
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-6 sm:-my-px sm:ms-8 sm:flex">
                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    Dashboard
                                </NavLink>
                                <NavLink
                                    :href="route('bazaar')"
                                    :active="route().current('bazaar')"
                                >
                                    Bazaar
                                </NavLink>
                                <NavLink
                                    :href="route('npc-flips')"
                                    :active="route().current('npc-flips')"
                                >
                                    NPC Flips
                                </NavLink>
                                <NavLink
                                    :href="route('profile-stats')"
                                    :active="route().current('profile-stats')"
                                >
                                    Profile Stats
                                </NavLink>
                                <NavLink
                                    :href="route('event-timer')"
                                    :active="route().current('event-timer')"
                                >
                                    Event Timer
                                </NavLink>
                                <template v-if="isTestingAdmin">
                                    <NavLink
                                        :href="route('dungeon-party')"
                                        :active="route().current('dungeon-party')"
                                    >
                                        Party Finder
                                    </NavLink>
                                    <NavLink
                                        :href="route('portfolio')"
                                        :active="route().current('portfolio')"
                                    >
                                        Portfolio
                                    </NavLink>
                                    <NavLink
                                        :href="route('crafting')"
                                        :active="route().current('crafting')"
                                    >
                                        Crafting
                                    </NavLink>
                                    <NavLink
                                        :href="route('bin-sniper')"
                                        :active="route().current('bin-sniper')"
                                    >
                                        BIN Sniper
                                    </NavLink>
                                </template>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <div
                                v-if="currentMayor?.name"
                                class="mr-3 hidden rounded-lg border border-border bg-surface-700/70 px-2.5 py-1.5 text-[11px] leading-tight text-neutral lg:block"
                            >
                                <div class="font-semibold text-white">Current Mayor: {{ currentMayor.name }}</div>
                                <div>{{ mayorPerkSummary }}</div>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex items-center justify-center p-2 text-neutral hover:text-white focus:outline-none"
                            >
                                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                    <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('bazaar')" :active="route().current('bazaar')">
                            Bazaar
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('npc-flips')" :active="route().current('npc-flips')">
                            NPC Flips
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('profile-stats')" :active="route().current('profile-stats')">
                            Profile Stats
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('event-timer')" :active="route().current('event-timer')">
                            Event Timer
                        </ResponsiveNavLink>
                        <template v-if="isTestingAdmin">
                            <ResponsiveNavLink :href="route('dungeon-party')" :active="route().current('dungeon-party')">
                                Party Finder
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('portfolio')" :active="route().current('portfolio')">
                                Portfolio
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('crafting')" :active="route().current('crafting')">
                                Crafting
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('bin-sniper')" :active="route().current('bin-sniper')">
                                BIN Sniper
                            </ResponsiveNavLink>
                        </template>
                    </div>

                    <div class="border-t border-border pb-1 pt-4">
                        <div v-if="currentMayor?.name" class="mx-4 rounded-lg border border-border bg-surface-700/70 px-3 py-2 text-[11px] text-neutral">
                            <div class="font-semibold text-white">Current Mayor: {{ currentMayor.name }}</div>
                            <div>{{ mayorPerkSummary }}</div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header class="border-b border-border bg-surface-800" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
