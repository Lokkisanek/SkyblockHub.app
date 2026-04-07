<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center rounded-md border border-border bg-surface-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-neutral shadow-sm transition duration-150 ease-in-out hover:bg-surface-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-border focus:ring-offset-2 focus:ring-offset-surface-800 disabled:opacity-25"
    >
        <slot />
    </button>
</template>
