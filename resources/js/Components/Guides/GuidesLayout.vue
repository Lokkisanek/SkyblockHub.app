<script setup>
import { ref } from 'vue';
import GuidesSidebar from '@/Components/Guides/GuidesSidebar.vue';
import GuidesSearch from '@/Components/Guides/GuidesSearch.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    currentSlug: {
        type: String,
        default: null,
    },
    guideGroups: {
        type: Array,
        default: () => [],
    },
    guideExternalTools: {
        type: Array,
        default: () => [],
    },
    guideSearchIndex: {
        type: Array,
        default: () => [],
    },
});

const sidebarOpen = ref(false);
const searchRef = ref(null);

function openSearch() {
    searchRef.value?.openModal();
}
</script>

<template>
    <div class="guides-shell">
        <div aria-hidden="true" class="guides-bg-scrim" />

        <header class="guides-header">
            <div class="guides-header-row">
                <div class="guides-header-start">
                    <button
                        type="button"
                        class="guides-menu-btn lg:hidden"
                        aria-label="Toggle guide navigation"
                        @click="sidebarOpen = !sidebarOpen"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <Link :href="route('guides')" class="guides-brand">SkyBlock Guides</Link>
                </div>
                <GuidesSearch ref="searchRef" :search-index="guideSearchIndex" class="guides-header-search" />
                <button type="button" class="guides-search-mobile lg:hidden" aria-label="Search guides" @click="openSearch">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </button>
            </div>
        </header>

        <div class="guides-body">
            <div
                v-if="sidebarOpen"
                class="guides-sidebar-backdrop lg:hidden"
                @click="sidebarOpen = false"
            />
            <GuidesSidebar
                :current-slug="currentSlug"
                :guide-groups="guideGroups"
                :guide-external-tools="guideExternalTools"
                class="guides-sidebar-panel"
                :class="{ 'guides-sidebar-panel--open': sidebarOpen }"
            />
            <main class="guides-main">
                <slot />
            </main>
        </div>

        <footer class="guides-footer">
            <p>SkyBlock Guides · Not affiliated with Hypixel or Mojang.</p>
            <Link :href="route('guides.submit')" class="guides-footer-link">Add guide</Link>
        </footer>
    </div>
</template>
