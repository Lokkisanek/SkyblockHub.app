<script setup>
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
});
</script>

<template>
    <aside class="guides-sidebar">
        <nav class="guides-sidebar-nav" aria-label="Guide topics">
            <div v-for="group in guideGroups" :key="group.id" class="guides-sidebar-group">
                <p class="guides-sidebar-label">{{ group.label }}</p>
                <ul class="guides-sidebar-list">
                    <li v-for="topic in group.topics" :key="topic.slug">
                        <Link
                            :href="route('guides.show', topic.slug)"
                            class="guides-sidebar-link"
                            :class="{ 'guides-sidebar-link--active': currentSlug === topic.slug }"
                        >
                            {{ topic.title }}
                        </Link>
                    </li>
                </ul>
            </div>

            <div class="guides-sidebar-group guides-sidebar-group--external">
                <p class="guides-sidebar-label">External tools</p>
                <ul class="guides-sidebar-list">
                    <li v-for="tool in guideExternalTools" :key="tool.url">
                        <a
                            :href="tool.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="guides-sidebar-link guides-sidebar-link--external"
                        >
                            {{ tool.label }}
                            <span aria-hidden="true">↗</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
    </aside>
</template>
