<script setup>
import { computed } from 'vue';

const props = defineProps({
    text: {
        type: String,
        default: '',
    },
});

const parts = computed(() => {
    const source = String(props.text ?? '');
    const tokens = [];
    const linkPattern = /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g;
    let lastIndex = 0;
    let match;

    while ((match = linkPattern.exec(source)) !== null) {
        if (match.index > lastIndex) {
            tokens.push({ type: 'text', text: source.slice(lastIndex, match.index) });
        }

        tokens.push({
            type: 'link',
            text: match[1],
            url: match[2],
        });

        lastIndex = match.index + match[0].length;
    }

    if (lastIndex < source.length) {
        tokens.push({ type: 'text', text: source.slice(lastIndex) });
    }

    return tokens.length ? tokens : [{ type: 'text', text: source }];
});
</script>

<template>
    <template v-for="(part, index) in parts" :key="`${part.type}-${index}`">
        <a
            v-if="part.type === 'link'"
            :href="part.url"
            target="_blank"
            rel="noopener noreferrer"
            class="guides-inline-link"
        >
            {{ part.text }}
        </a>
        <template v-else>{{ part.text }}</template>
    </template>
</template>
