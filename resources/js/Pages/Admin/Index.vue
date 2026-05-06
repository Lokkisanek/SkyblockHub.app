<script setup>
import AnalyticsPage from '../Analitics/Index.vue';

defineOptions({
    inheritAttrs: false,
});
</script>

<template>
    <AnalyticsPage v-bind="$attrs" />
</template>