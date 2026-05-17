<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GuideBlockEditor from '@/Components/Guides/GuideBlockEditor.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    submission: {
        type: Object,
        required: true,
    },
    categories: {
        type: Object,
        default: () => ({}),
    },
});

const categoryOptions = computed(() => Object.entries(props.categories));
const isPending = computed(() => props.submission.status === 'pending');

const form = useForm({
    title: props.submission.title,
    slug: props.submission.slug,
    description: props.submission.description,
    category: props.submission.category,
    sections: props.submission.sections,
    useful_links: props.submission.usefulLinks ?? [],
    admin_notes: props.submission.adminNotes ?? '',
});

function saveDraft() {
    form.patch(route('admin.guides.submissions.update', props.submission.id), {
        preserveScroll: true,
    });
}

function approve() {
    form.post(route('admin.guides.submissions.approve', props.submission.id), {
        preserveScroll: true,
    });
}

function reject() {
    form.post(route('admin.guides.submissions.reject', props.submission.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Review: ${submission.title}`" />
    <AuthenticatedLayout>
        <div class="min-h-screen px-4 py-8 sm:px-6 lg:px-8">
            <form class="mx-auto max-w-7xl" @submit.prevent="saveDraft">
                <section class="hero-card mb-6">
                    <div>
                        <p class="hero-kicker">Admin review</p>
                        <h1 class="hero-title">{{ submission.title }}</h1>
                        <p class="hero-copy">
                            {{ submission.type === 'edit' ? 'Edit suggestion' : 'New article' }}
                            · {{ submission.status }}
                            <span v-if="submission.guide"> · current guide: {{ submission.guide.title }}</span>
                        </p>
                    </div>
                    <div class="guide-admin-actions">
                        <Link :href="route('admin.guides.submissions')" class="guide-editor-mini">Back</Link>
                        <button type="submit" class="guide-editor-mini" :disabled="form.processing || !isPending">Save draft</button>
                        <button type="button" class="guides-action-btn" :disabled="form.processing || !isPending" @click="approve">
                            Approve
                        </button>
                        <button type="button" class="guide-editor-mini guide-editor-danger" :disabled="form.processing || !isPending" @click="reject">
                            Reject
                        </button>
                    </div>
                </section>

                <div class="guide-form-card">
                    <label class="guide-editor-label">
                        Title
                        <input v-model="form.title" class="guide-editor-input" type="text" required>
                    </label>
                    <label class="guide-editor-label">
                        Slug
                        <input v-model="form.slug" class="guide-editor-input" type="text" required>
                    </label>
                    <label class="guide-editor-label">
                        Category
                        <select v-model="form.category" class="guide-editor-select">
                            <option v-for="[value, label] in categoryOptions" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </label>
                    <label class="guide-editor-label">
                        Submitter
                        <input
                            class="guide-editor-input"
                            type="text"
                            :value="[submission.submitterName || 'anonymous', submission.submitterContact].filter(Boolean).join(' · ')"
                            disabled
                        >
                    </label>
                    <label class="guide-editor-label guide-form-full">
                        Description
                        <textarea v-model="form.description" class="guide-editor-textarea" rows="3" />
                    </label>
                    <label class="guide-editor-label guide-form-full">
                        Admin notes
                        <textarea v-model="form.admin_notes" class="guide-editor-textarea" rows="3" placeholder="Reason for edits/rejecting, internal note..." />
                    </label>
                </div>

                <div v-if="Object.keys(form.errors).length" class="guide-form-errors">
                    <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
                </div>

                <GuideBlockEditor v-model:sections="form.sections" v-model:useful-links="form.useful_links" />
            </form>
        </div>
    </AuthenticatedLayout>
</template>
