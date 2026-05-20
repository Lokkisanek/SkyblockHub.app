<script setup>
import { ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from '@/strings/useI18n';

const { t } = useI18n();

const fieldClass =
    'w-full rounded-xl border border-border/80 bg-surface-800/80 px-3 py-2.5 text-sm text-white placeholder:text-neutral/80 transition focus:border-profit/70 focus:outline-none focus:ring-2 focus:ring-profit/25';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    flash: {
        type: Object,
        default: () => ({ message: null, variant: 'success' }),
    },
});

const flashBanner = ref(props.flash?.message ?? null);
const flashVariant = ref(props.flash?.variant ?? 'success');

watch(
    () => props.flash?.message,
    (message) => {
        if (message) {
            flashBanner.value = message;
            flashVariant.value = props.flash?.variant ?? 'success';
        }
    },
);

const form = useForm({
    minecraft_username: '',
    category: '',
    description: '',
    evidence: '',
    submitter_name: '',
    submitter_contact: '',
});

function submit() {
    form.post(route('trust-index.report.store'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('trustIndex.reportPageTitle')" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            <form class="guides-article" @submit.prevent="submit">
                <nav class="guides-breadcrumb" aria-label="Breadcrumb">
                    <Link :href="route('trust-index')">{{ t('trustIndex.title') }}</Link>
                    <span aria-hidden="true">/</span>
                    <span>{{ t('trustIndex.reportHeading') }}</span>
                </nav>

                <div class="guides-title-row">
                    <div>
                        <p class="guides-eyebrow">{{ t('trustIndex.kicker') }}</p>
                        <h1 class="guides-page-title">{{ t('trustIndex.reportHeading') }}</h1>
                        <p class="guides-lead mb-0">{{ t('trustIndex.reportSubheading') }}</p>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <button
                            type="submit"
                            class="guides-action-btn guides-action-btn--primary"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? t('trustIndex.formSubmitting') : t('trustIndex.formSubmitReport') }}
                        </button>
                        <Link :href="route('trust-index')" class="guides-action-btn guides-action-btn--subtle">
                            {{ t('trustIndex.backToList') }}
                        </Link>
                    </div>
                </div>

                <div
                    v-if="flashBanner"
                    :class="[
                        'mt-4 rounded-lg px-4 py-3 text-sm',
                        flashVariant === 'success'
                            ? 'border border-profit/35 bg-profit/10 text-profit'
                            : 'border border-loss/50 bg-loss/10 text-loss',
                    ]"
                    role="status"
                >
                    {{ flashBanner }}
                </div>

                <div class="guide-form-card mt-4">
                    <label class="flex flex-col gap-1.5">
                        <span class="text-sm font-medium text-white/70">{{ t('trustIndex.formScammerUsername') }}</span>
                        <input
                            v-model="form.minecraft_username"
                            :class="fieldClass"
                            type="text"
                            required
                            maxlength="16"
                            autocomplete="off"
                        />
                    </label>
                    <label class="flex flex-col gap-1.5">
                        <span class="text-sm font-medium text-white/70">{{ t('trustIndex.formCategory') }}</span>
                        <select v-model="form.category" :class="fieldClass" required>
                            <option value="" disabled>{{ t('trustIndex.formCategoryPlaceholder') }}</option>
                            <option v-for="cat in categories" :key="cat.key" :value="cat.key">
                                {{ cat.label }}
                            </option>
                        </select>
                    </label>
                    <label class="guide-form-full flex flex-col gap-1.5">
                        <span class="text-sm font-medium text-white/70">{{ t('trustIndex.formDescription') }}</span>
                        <textarea
                            v-model="form.description"
                            :class="[fieldClass, 'min-h-[7.5rem] resize-y']"
                            rows="5"
                            required
                            :placeholder="t('trustIndex.formDescriptionReportPlaceholder')"
                        />
                    </label>
                    <label class="guide-form-full flex flex-col gap-1.5">
                        <span class="text-sm font-medium text-white/70">{{ t('trustIndex.formEvidence') }}</span>
                        <textarea
                            v-model="form.evidence"
                            :class="[fieldClass, 'min-h-[5rem] resize-y']"
                            rows="3"
                            :placeholder="t('trustIndex.formEvidencePlaceholder')"
                        />
                    </label>
                    <label class="flex flex-col gap-1.5">
                        <span class="text-sm font-medium text-white/70">{{ t('trustIndex.formYourName') }}</span>
                        <input v-model="form.submitter_name" :class="fieldClass" type="text" />
                    </label>
                    <label class="flex flex-col gap-1.5">
                        <span class="text-sm font-medium text-white/70">{{ t('trustIndex.formContact') }}</span>
                        <input
                            v-model="form.submitter_contact"
                            :class="fieldClass"
                            type="text"
                            :placeholder="t('trustIndex.formContactPlaceholder')"
                        />
                    </label>
                </div>

                <div v-if="Object.keys(form.errors).length" class="guide-form-errors">
                    <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
