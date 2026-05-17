<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    submissions: {
        type: Array,
        default: () => [],
    },
});

function statusClass(status) {
    return {
        pending: 'guide-admin-pill--pending',
        approved: 'guide-admin-pill--approved',
        rejected: 'guide-admin-pill--rejected',
    }[status] ?? 'guide-admin-pill--pending';
}
</script>

<template>
    <Head title="Guide submissions" />
    <AuthenticatedLayout>
        <div class="min-h-screen px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-6xl">
                <section class="hero-card mb-6">
                    <div>
                        <p class="hero-kicker">Admin</p>
                        <h1 class="hero-title">Guide submissions</h1>
                        <p class="hero-copy">Review community articles and edit suggestions before they go live.</p>
                    </div>
                    <Link :href="route('admin.index')" class="guides-action-btn">Admin home</Link>
                </section>

                <section class="guide-admin-card">
                    <div v-if="!submissions.length" class="guide-admin-empty">
                        No submissions yet.
                    </div>
                    <div v-else class="guide-admin-list">
                        <article v-for="submission in submissions" :key="submission.id" class="guide-admin-row">
                            <div>
                                <div class="guide-admin-row-head">
                                    <span class="guide-admin-pill" :class="statusClass(submission.status)">
                                        {{ submission.status }}
                                    </span>
                                    <span class="guide-admin-type">{{ submission.type === 'edit' ? 'Edit suggestion' : 'New article' }}</span>
                                </div>
                                <h2 class="guide-admin-title">{{ submission.title }}</h2>
                                <p class="guide-admin-meta">
                                    {{ submission.slug || 'no-slug' }} · {{ submission.createdAt }}
                                    <span v-if="submission.guide"> · edits {{ submission.guide.title }}</span>
                                </p>
                                <p v-if="submission.submitterName || submission.submitterContact" class="guide-admin-meta">
                                    From {{ submission.submitterName || 'anonymous' }}
                                    <span v-if="submission.submitterContact"> · {{ submission.submitterContact }}</span>
                                </p>
                            </div>
                            <Link :href="route('admin.guides.submissions.show', submission.id)" class="guides-action-btn">
                                Review
                            </Link>
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
