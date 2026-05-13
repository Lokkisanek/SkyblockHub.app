<script setup>
defineProps({
    name: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <!-- Unified stroke icons (matches nav / UI icon tone) -->
    <svg
        v-if="name === 'bazaar'"
        class="h-[1.15em] w-[1.15em] shrink-0"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.6"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path d="M4 19V5" />
        <path d="M4 19h16" />
        <path d="M8 16V9" />
        <path d="M12 16v-5" />
        <path d="M16 16V7" />
    </svg>
    <svg
        v-else-if="name === 'npc'"
        class="h-[1.15em] w-[1.15em] shrink-0"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.6"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path d="M7 7h10v10H7z" />
        <path d="M17 7V5H9" />
        <path d="M7 17v2h8" />
        <path d="m10 10 4 4" />
        <path d="m14 10-4 4" />
    </svg>
    <svg
        v-else-if="name === 'profiles'"
        class="h-[1.15em] w-[1.15em] shrink-0"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.6"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <circle cx="12" cy="8" r="3.25" />
        <path d="M6 20v-1.25a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4V20" />
    </svg>
    <svg
        v-else-if="name === 'events'"
        class="h-[1.15em] w-[1.15em] shrink-0"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.6"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <circle cx="12" cy="12" r="8.25" />
        <path d="M12 7.25V12l3.5 2" />
    </svg>
    <svg
        v-else-if="name === 'mayors'"
        class="h-[1.15em] w-[1.15em] shrink-0"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.6"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path d="M5 20V9l7-4 7 4v11" />
        <path d="M9 20v-6h6v6" />
        <path d="M5 12h14" />
    </svg>
</template>
