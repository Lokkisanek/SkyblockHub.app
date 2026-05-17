<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GuideBlockEditor from '@/Components/Guides/GuideBlockEditor.vue';
import GuidesLayout from '@/Components/Guides/GuidesLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    initialGuide: {
        type: Object,
        required: true,
    },
    categories: {
        type: Object,
        default: () => ({}),
    },
    guideGroups: {
        type: Array,
        default: () => [],
    },
    guideExternalTools: {
        type: Array,
        default: () => [],
    },
    guideSearchIndex: {
        type: Array,
        default: () => [],
    },
});

const categoryOptions = computed(() => Object.entries(props.categories));

const form = useForm({
    submitter_name: '',
    submitter_contact: '',
    title: props.initialGuide.title,
    slug: props.initialGuide.slug,
    description: props.initialGuide.description,
    category: props.initialGuide.category,
    sections: props.initialGuide.sections,
    useful_links: props.initialGuide.usefulLinks ?? [],
});

function submit() {
    form.post(route('guides.submissions.store'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Submit guide" />
    <AuthenticatedLayout>
        <GuidesLayout
            :guide-groups="guideGroups"
            :guide-external-tools="guideExternalTools"
            :guide-search-index="guideSearchIndex"
        >
            <form class="guides-article guides-article--editor" @submit.prevent="submit">
                <nav class="guides-breadcrumb" aria-label="Breadcrumb">
                    <Link :href="route('guides')">Guides</Link>
                    <span aria-hidden="true">/</span>
                    <span>Submit guide</span>
                </nav>

                <div class="guides-title-row">
                    <div>
                        <p class="guides-eyebrow">Community submission</p>
                        <h1 class="guides-page-title">Add a SkyBlock guide</h1>
                        <p class="guides-lead">
                            Build a structured article with sections, citations, tables, and source links.
                            An admin reviews it before publishing.
                        </p>
                    </div>
                    <button type="submit" class="guides-action-btn guides-action-btn--primary" :disabled="form.processing">
                        {{ form.processing ? 'Submitting...' : 'Send for review' }}
                    </button>
                </div>

                <div class="guide-form-card">
                    <label class="guide-editor-label">
                        Your name (optional)
                        <input v-model="form.submitter_name" class="guide-editor-input" type="text" autocomplete="name">
                    </label>
                    <label class="guide-editor-label">
                        Contact (optional)
                        <input v-model="form.submitter_contact" class="guide-editor-input" type="text" placeholder="Discord, email, or Minecraft name">
                    </label>
                    <label class="guide-editor-label">
                        Title
                        <input v-model="form.title" class="guide-editor-input" type="text" required>
                    </label>
                    <label class="guide-editor-label">
                        Slug
                        <input v-model="form.slug" class="guide-editor-input" type="text" placeholder="auto-filled from title if empty">
                    </label>
                    <label class="guide-editor-label">
                        Category
                        <select v-model="form.category" class="guide-editor-select">
                            <option v-for="[value, label] in categoryOptions" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </label>
                    <label class="guide-editor-label guide-form-full">
                        Description
                        <textarea v-model="form.description" class="guide-editor-textarea" rows="3" />
                    </label>
                </div>

                <div v-if="Object.keys(form.errors).length" class="guide-form-errors">
                    <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
                </div>

                <GuideBlockEditor v-model:sections="form.sections" v-model:useful-links="form.useful_links" />
            </form>
        </GuidesLayout>
    </AuthenticatedLayout>
</template>
