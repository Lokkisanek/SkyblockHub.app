<script setup>
import { computed } from 'vue';

const props = defineProps({
    tone: {
        type: String,
        default: 'light',
    },
    alt: {
        type: String,
        default: 'SkyblockHub logo',
    },
});

const src = computed(() => (props.tone === 'dark' ? '/img/logo-black.webp' : '/img/logo-white.webp'));
</script>

<template>
    <img :src="src" :alt="alt" />
</template>
