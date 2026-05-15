<script setup>
import { computed } from 'vue';
import { colorCodeToHtml } from '@/utils/minecraftColors';

const props = defineProps({
    text: { type: String, default: '' },
    tag: { type: String, default: 'span' },
});

const html = computed(() => colorCodeToHtml(props.text));
</script>

<template>
    <component :is="tag" class="mc-text" v-html="html" />
</template>
