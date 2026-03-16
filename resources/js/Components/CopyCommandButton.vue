<script setup>
import { ref } from 'vue';

const props = defineProps({
    productId: {
        type: String,
        required: true,
    },
});

const copied = ref(false);

function command() {
    return `/bz buy "${props.productId}" 1`;
}

async function copyCommand() {
    try {
        await navigator.clipboard.writeText(command());
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        // Fallback for non-HTTPS / older browsers
        const textarea = document.createElement('textarea');
        textarea.value = command();
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    }
}
</script>

<template>
    <div class="inline-flex items-center">
        <button
            @click="copyCommand"
            class="px-2 py-0.5 text-[10px] font-medium border border-border rounded-none transition-colors duration-0"
            :class="copied
                ? 'bg-[#2a3a2a] text-profit border-profit'
                : 'bg-surface-700 text-neutral hover:text-white'"
        >
            {{ copied ? 'Copied!' : 'Copy' }}
        </button>
    </div>
</template>
