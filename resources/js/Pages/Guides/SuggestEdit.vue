<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GuideBlockEditor from '@/Components/Guides/GuideBlockEditor.vue';
import GuidesLayout from '@/Components/Guides/GuidesLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    guide: {
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
    title: props.guide.title,
    slug: props.guide.slug,
    description: props.guide.description,
    category: props.guide.category,
    sections: props.guide.sections,
    useful_links: props.guide.usefulLinks ?? [],
});

function submit() {
    form.post(route('guides.suggest-edit.store', props.guide.slug), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Suggest edit: ${guide.title}`" />
    <AuthenticatedLayout>
        <GuidesLayout
            :current-slug="guide.slug"
            :guide-groups="guideGroups"
            :guide-external-tools="guideExternalTools"
            :guide-search-index="guideSearchIndex"
        >
            <form class="guides-article guides-article--editor" @submit.prevent="submit">
                <nav class="guides-breadcrumb" aria-label="Breadcrumb">
                    <Link :href="route('guides')">Guides</Link>
                    <span aria-hidden="true">/</span>
                    <Link :href="route('guides.show', guide.slug)">{{ guide.title }}</Link>
                    <span aria-hidden="true">/</span>
                    <span>Suggest edit</span>
                </nav>

                <div class="guides-title-row">
                    <div>
                        <p class="guides-eyebrow">Community edit</p>
                        <h1 class="guides-page-title">Suggest an improvement</h1>
                        <p class="guides-lead">
                            Update the article, add citations, and attach source links.
                            Your edit goes to admin review before publishing.
                        </p>
                    </div>
                    <button type="submit" class="guides-action-btn guides-action-btn--primary" :disabled="form.processing">
                        {{ form.processing ? 'Submitting...' : 'Submit edit' }}
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
                        <input v-model="form.slug" class="guide-editor-input" type="text">
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
