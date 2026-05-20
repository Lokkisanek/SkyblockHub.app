<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    submission: {
        type: Object,
        required: true,
    },
});

const isPending = computed(() => props.submission.status === 'pending');

const typeLabel = computed(() => (props.submission.type === 'appeal' ? 'Appeal' : 'Scam report'));

const form = useForm({
    admin_notes: props.submission.adminNotes ?? '',
});

function approve() {
    form.post(route('admin.trust-index.submissions.approve', props.submission.id), {
        preserveScroll: true,
    });
}

function reject() {
    form.post(route('admin.trust-index.submissions.reject', props.submission.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Trust: ${submission.minecraftUsername}`" />
    <AuthenticatedLayout>
        <div class="min-h-screen px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl">
                <section class="hero-card mb-6">
                    <div>
                        <p class="hero-kicker">Trust Index review</p>
                        <h1 class="hero-title">{{ submission.minecraftUsername }}</h1>
                        <p class="hero-copy">
                            {{ typeLabel }} · {{ submission.status }}
                            <span v-if="submission.categoryLabel"> · {{ submission.categoryLabel }}</span>
                        </p>
                    </div>
                    <div class="guide-admin-actions">
                        <Link :href="route('admin.trust-index.submissions')" class="guide-editor-mini">Back</Link>
                        <button
                            type="button"
                            class="guides-action-btn"
                            :disabled="form.processing || !isPending"
                            @click="approve"
                        >
                            Approve / triaged
                        </button>
                        <button
                            type="button"
                            class="guide-editor-mini guide-editor-danger"
                            :disabled="form.processing || !isPending"
                            @click="reject"
                        >
                            Reject
                        </button>
                    </div>
                </section>

                <div class="guide-form-card">
                    <label class="guide-editor-label guide-form-full">
                        Description
                        <textarea class="guide-editor-textarea" rows="8" :value="submission.description" readonly />
                    </label>
                    <label v-if="submission.evidence" class="guide-editor-label guide-form-full">
                        Evidence / links
                        <textarea class="guide-editor-textarea" rows="4" :value="submission.evidence" readonly />
                    </label>
                    <label class="guide-editor-label guide-form-full">
                        Submitter (form)
                        <input
                            class="guide-editor-input"
                            type="text"
                            :value="
                                [submission.submitterName || '—', submission.submitterContact || ''].filter(Boolean).join(' · ')
                            "
                            readonly
                        />
                    </label>
                    <label v-if="submission.submitterUser" class="guide-editor-label guide-form-full">
                        Logged-in account
                        <input
                            class="guide-editor-input"
                            type="text"
                            :value="
                                [
                                    submission.submitterUser.minecraftUsername || '',
                                    submission.submitterUser.discordUsername || '',
                                ]
                                    .filter(Boolean)
                                    .join(' · ') || '#' + submission.submitterUser.id
                            "
                            readonly
                        />
                    </label>
                    <label class="guide-editor-label guide-form-full">
                        Internal notes
                        <textarea
                            v-model="form.admin_notes"
                            class="guide-editor-textarea"
                            rows="3"
                            placeholder="Optional note stored with the decision…"
                            :disabled="!isPending"
                        />
                    </label>
                </div>

                <div v-if="Object.keys(form.errors).length" class="guide-form-errors">
                    <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
                </div>

                <p v-if="!isPending && submission.adminNotes" class="mt-4 text-sm text-white/60">
                    Saved notes: {{ submission.adminNotes }}
                </p>
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
