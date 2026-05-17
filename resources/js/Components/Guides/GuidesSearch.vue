<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    searchIndex: {
        type: Array,
        default: () => [],
    },
});

const open = ref(false);
const query = ref('');
const activeIndex = ref(0);
const inputRef = ref(null);

const results = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) {
        return props.searchIndex.slice(0, 12);
    }

    return props.searchIndex
        .filter((entry) => entry.text.toLowerCase().includes(q))
        .slice(0, 20);
});

watch(query, () => {
    activeIndex.value = 0;
});

watch(open, async (isOpen) => {
    if (isOpen) {
        await nextTick();
        inputRef.value?.focus();
    } else {
        query.value = '';
        activeIndex.value = 0;
    }
});

function openModal() {
    open.value = true;
}

function closeModal() {
    open.value = false;
}

function goTo(entry) {
    if (!entry) return;
    closeModal();
    router.visit(route('guides.show', entry.slug));
}

function onKeydown(e) {
    if (e.key === 'k' && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        open.value = !open.value;
        return;
    }

    if (!open.value) return;

    if (e.key === 'Escape') {
        e.preventDefault();
        closeModal();
        return;
    }

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIndex.value = Math.min(activeIndex.value + 1, results.value.length - 1);
        return;
    }

    if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex.value = Math.max(activeIndex.value - 1, 0);
        return;
    }

    if (e.key === 'Enter') {
        e.preventDefault();
        goTo(results.value[activeIndex.value]);
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
});

defineExpose({ openModal });
</script>

<template>
    <div class="guides-search">
        <button type="button" class="guides-search-trigger" @click="openModal">
            <span class="guides-search-trigger-text">Search guides…</span>
            <kbd class="guides-search-kbd">⌘K</kbd>
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                class="guides-search-overlay"
                role="dialog"
                aria-modal="true"
                aria-label="Search guides"
                @click.self="closeModal"
            >
                <div class="guides-search-panel">
                    <input
                        ref="inputRef"
                        v-model="query"
                        type="search"
                        class="guides-search-input"
                        placeholder="Search guides…"
                        autocomplete="off"
                    >

                    <ul v-if="results.length" class="guides-search-results" role="listbox">
                        <li
                            v-for="(entry, i) in results"
                            :key="`${entry.slug}-${entry.title}-${i}`"
                            role="option"
                            :aria-selected="i === activeIndex"
                        >
                            <button
                                type="button"
                                class="guides-search-result"
                                :class="{ 'guides-search-result--active': i === activeIndex }"
                                @click="goTo(entry)"
                            >
                                <span class="guides-search-result-title">{{ entry.title }}</span>
                                <span class="guides-search-result-crumb">{{ entry.breadcrumb }}</span>
                            </button>
                        </li>
                    </ul>
                    <p v-else class="guides-search-empty">No matches.</p>
                </div>
            </div>
        </Teleport>
    </div>
</template>
