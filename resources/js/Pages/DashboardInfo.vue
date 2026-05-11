<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <Head :title="t('dashboardInfo.title')" />

    <AuthenticatedLayout>
        <div class="pt-14 pb-20 sm:pt-16 sm:pb-24">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-amber-200/70">Dashboard Beta</p>
                        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-white sm:text-4xl">{{ t('dashboardInfo.title') }}</h1>
                        <p class="mt-2 max-w-3xl text-sm text-white/55 sm:text-base">{{ t('dashboardInfo.subtitle') }}</p>
                    </div>

                    <Link
                        :href="route('dashboard')"
                        class="inline-flex items-center rounded-full border border-white/15 bg-white/5 px-4 py-2 text-sm font-medium text-white/80 transition hover:border-white/30 hover:bg-white/10 hover:text-white"
                    >
                        {{ t('dashboardInfo.backToDashboard') }}
                    </Link>
                </header>

                <section class="grid gap-4 md:grid-cols-2">
                    <article class="info-card" style="border-left: 2px solid rgba(16, 185, 129, 0.35);">
                        <p class="info-kicker text-emerald-300/85">{{ t('dashboardInfo.whatIsKicker') }}</p>
                        <h2 class="info-title">{{ t('dashboardInfo.whatIsTitle') }}</h2>
                        <p class="info-body">{{ t('dashboardInfo.whatIsBody') }}</p>
                    </article>

                    <article class="info-card" style="border-left: 2px solid rgba(99, 102, 241, 0.35);">
                        <p class="info-kicker text-indigo-300/85">{{ t('dashboardInfo.howItWorksKicker') }}</p>
                        <h2 class="info-title">{{ t('dashboardInfo.howItWorksTitle') }}</h2>
                        <p class="info-body">{{ t('dashboardInfo.howItWorksBody') }}</p>
                    </article>
                </section>

                <section class="mt-4 grid gap-4 md:grid-cols-2">
                    <article class="info-card" style="border-left: 2px solid rgba(245, 158, 11, 0.35);">
                        <p class="info-kicker text-amber-300/85">{{ t('dashboardInfo.progressKicker') }}</p>
                        <h2 class="info-title">{{ t('dashboardInfo.progressTitle') }}</h2>
                        <ul class="info-list">
                            <li>{{ t('dashboardInfo.progressPoint1') }}</li>
                            <li>{{ t('dashboardInfo.progressPoint2') }}</li>
                            <li>{{ t('dashboardInfo.progressPoint3') }}</li>
                            <li>{{ t('dashboardInfo.progressPoint4') }}</li>
                        </ul>
                    </article>

                    <article class="info-card" style="border-left: 2px solid rgba(56, 189, 248, 0.35);">
                        <p class="info-kicker text-sky-300/85">{{ t('dashboardInfo.roadmapKicker') }}</p>
                        <h2 class="info-title">{{ t('dashboardInfo.roadmapTitle') }}</h2>
                        <ul class="info-list">
                            <li>{{ t('dashboardInfo.roadmapPoint1') }}</li>
                            <li>{{ t('dashboardInfo.roadmapPoint2') }}</li>
                            <li>{{ t('dashboardInfo.roadmapPoint3') }}</li>
                            <li>{{ t('dashboardInfo.roadmapPoint4') }}</li>
                        </ul>
                    </article>
                </section>

                <section class="mt-4">
                    <article class="info-card" style="border-left: 2px solid rgba(148, 163, 184, 0.35);">
                        <p class="info-kicker text-slate-300/85">{{ t('dashboardInfo.feedbackKicker') }}</p>
                        <h2 class="info-title">{{ t('dashboardInfo.feedbackTitle') }}</h2>
                        <p class="info-body">{{ t('dashboardInfo.feedbackBody') }}</p>
                        <p class="mt-2 text-sm text-white/70">{{ t('dashboardInfo.feedbackDiscord') }}</p>
                        <a
                            href="https://discord.gg/TkavZSfUAd"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-4 inline-flex items-center rounded-full border border-[#5865F2]/45 bg-[#5865F2]/20 px-4 py-2 text-sm font-semibold text-white transition hover:border-[#5865F2]/70 hover:bg-[#5865F2]/35"
                        >
                            {{ t('dashboardInfo.joinDiscord') }}
                        </a>
                    </article>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.info-card {
    background: rgba(15, 18, 25, 0.65);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.04);
    border-radius: 0.75rem;
    padding: 1.25rem 1.5rem;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
}

.info-kicker {
    font-size: 0.64rem;
    font-weight: 700;
    letter-spacing: 0.22em;
    text-transform: uppercase;
}

.info-title {
    margin-top: 0.65rem;
    font-size: 1.08rem;
    font-weight: 600;
    color: #fff;
}

.info-body {
    margin-top: 0.75rem;
    font-size: 0.9rem;
    line-height: 1.65;
    color: rgba(255, 255, 255, 0.62);
}

.info-list {
    margin-top: 0.8rem;
    display: grid;
    gap: 0.48rem;
    padding-left: 1.05rem;
    color: rgba(255, 255, 255, 0.68);
    font-size: 0.9rem;
    line-height: 1.55;
}
</style>
