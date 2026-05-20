<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    submissions: {
        type: Array,
        default: () => [],
    },
    statusMessage: {
        type: String,
        default: null,
    },
});

function statusClass(status) {
    return {
        pending: 'guide-admin-pill--pending',
        approved: 'guide-admin-pill--approved',
        rejected: 'guide-admin-pill--rejected',
    }[status] ?? 'guide-admin-pill--pending';
}

function typeLabel(type) {
    return type === 'appeal' ? 'Appeal' : 'Scam report';
}
</script>

<template>
    <Head title="Trust Index submissions" />
    <AuthenticatedLayout>
        <div class="min-h-screen px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-6xl">
                <section class="hero-card mb-6">
                    <div>
                        <p class="hero-kicker">Admin</p>
                        <h1 class="hero-title">Trust Index queue</h1>
                        <p class="hero-copy">
                            Review scam reports and delist appeals submitted from the Trust Index. Approving does not auto-update
                            the public list — use config or your workflow after triage.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link :href="route('admin.index')" class="guides-action-btn guides-action-btn--subtle">Admin home</Link>
                        <Link :href="route('admin.guides.submissions')" class="guides-action-btn guides-action-btn--subtle">
                            Guide queue
                        </Link>
                    </div>
                </section>

                <p
                    v-if="statusMessage"
                    class="mb-4 rounded-lg border border-profit/35 bg-profit/10 px-4 py-3 text-sm text-profit"
                    role="status"
                >
                    {{ statusMessage }}
                </p>

                <section class="guide-admin-card">
                    <div v-if="!submissions.length" class="guide-admin-empty">No submissions yet.</div>
                    <div v-else class="guide-admin-list">
                        <article v-for="submission in submissions" :key="submission.id" class="guide-admin-row">
                            <div>
                                <div class="guide-admin-row-head">
                                    <span class="guide-admin-pill" :class="statusClass(submission.status)">
                                        {{ submission.status }}
                                    </span>
                                    <span class="guide-admin-type">{{ typeLabel(submission.type) }}</span>
                                </div>
                                <h2 class="guide-admin-title">{{ submission.minecraftUsername }}</h2>
                                <p class="guide-admin-meta">
                                    #{{ submission.id }} · {{ submission.createdAt }}
                                    <template v-if="submission.categoryLabel"> · {{ submission.categoryLabel }}</template>
                                </p>
                                <p v-if="submission.submitterName || submission.submitterContact" class="guide-admin-meta">
                                    From {{ submission.submitterName || 'anonymous' }}
                                    <span v-if="submission.submitterContact"> · {{ submission.submitterContact }}</span>
                                </p>
                                <p v-if="submission.reviewedAt" class="guide-admin-meta">
                                    Reviewed {{ submission.reviewedAt }}
                                    <span v-if="submission.reviewedBy"> · by {{ submission.reviewedBy }}</span>
                                </p>
                            </div>
                            <Link :href="route('admin.trust-index.submissions.show', submission.id)" class="guides-action-btn">
                                Open
                            </Link>
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.hero-card {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.88), rgba(30, 41, 59, 0.9));
    border-radius: 18px;
    padding: 1.25rem;
}

.hero-kicker {
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.18em;
    font-size: 0.68rem;
    color: rgba(148, 163, 184, 0.9);
    font-weight: 700;
}

.hero-title {
    margin: 0.35rem 0 0;
    color: #f8fafc;
    font-size: clamp(1.35rem, 2.1vw, 2rem);
    line-height: 1.1;
    font-weight: 800;
}

.hero-copy {
    margin: 0.45rem 0 0;
    color: rgba(226, 232, 240, 0.82);
    max-width: 70ch;
    font-size: 0.92rem;
}
</style>
