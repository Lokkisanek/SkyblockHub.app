<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-md border-border bg-surface-700 text-white shadow-sm placeholder-neutral/50 focus:border-[#0bca51] focus:ring-[#0bca51]"
        v-model="model"
        ref="input"
    />
</template>
