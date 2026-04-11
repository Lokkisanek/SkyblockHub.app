<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <Head :title="t('privacyPage.title')" />

    <AuthenticatedLayout>
        <div class="pt-14 pb-20 sm:pt-16 sm:pb-24">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">

                <h1 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ t('privacyPage.heading') }}</h1>
                <p class="mt-2 text-sm text-white/40">{{ t('privacyPage.lastUpdated') }}</p>

                <div class="legal-content mt-10 space-y-8">

                    <section>
                        <h2>{{ t('privacyPage.section1Title') }}</h2>
                        <p>{{ t('privacyPage.section1Intro') }}</p>
                        <ul>
                            <li><strong>{{ t('privacyPage.section1Item1Label') }}</strong> {{ t('privacyPage.section1Item1Text') }}</li>
                            <li><strong>{{ t('privacyPage.section1Item2Label') }}</strong> {{ t('privacyPage.section1Item2Text') }}</li>
                            <li><strong>{{ t('privacyPage.section1Item3Label') }}</strong> {{ t('privacyPage.section1Item3Text') }}</li>
                        </ul>
                    </section>

                    <section>
                        <h2>{{ t('privacyPage.section2Title') }}</h2>
                        <ul>
                            <li>{{ t('privacyPage.section2Item1') }}</li>
                            <li>{{ t('privacyPage.section2Item2') }}</li>
                            <li>{{ t('privacyPage.section2Item3') }}</li>
                        </ul>
                    </section>

                    <section>
                        <h2>{{ t('privacyPage.section3Title') }}</h2>
                        <p>{{ t('privacyPage.section3Intro') }}</p>
                        <ul>
                            <li>{{ t('privacyPage.section3Item1') }}</li>
                            <li>{{ t('privacyPage.section3Item2') }}</li>
                        </ul>
                    </section>

                    <section>
                        <h2>{{ t('privacyPage.section4Title') }}</h2>
                        <p>{{ t('privacyPage.section4Text') }}</p>
                    </section>

                    <section>
                        <h2>{{ t('privacyPage.section5Title') }}</h2>
                        <p>{{ t('privacyPage.section5Text') }}</p>
                    </section>

                    <section>
                        <h2>{{ t('privacyPage.section6Title') }}</h2>
                        <p>{{ t('privacyPage.section6Intro') }}</p>
                        <ul>
                            <li>{{ t('privacyPage.section6Item1') }}</li>
                            <li>{{ t('privacyPage.section6Item2') }}</li>
                            <li>{{ t('privacyPage.section6Item3') }}</li>
                            <li>{{ t('privacyPage.section6Item4') }}</li>
                        </ul>
                    </section>

                    <section>
                        <h2>{{ t('privacyPage.section7Title') }}</h2>
                        <p>{{ t('privacyPage.section7Text') }} <a href="mailto:odehnalm.08@spst.eu" class="text-emerald-400/80 hover:text-emerald-300">odehnalm.08@spst.eu</a>.</p>
                    </section>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.legal-content h2 {
    font-size: 1rem;
    font-weight: 600;
    color: white;
    margin-bottom: 0.75rem;
}

.legal-content p {
    font-size: 0.875rem;
    line-height: 1.7;
    color: rgba(255, 255, 255, 0.5);
}

.legal-content ul {
    margin-top: 0.5rem;
    padding-left: 1.25rem;
    list-style-type: disc;
}

.legal-content li {
    font-size: 0.875rem;
    line-height: 1.7;
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 0.25rem;
}

.legal-content strong {
    color: rgba(255, 255, 255, 0.7);
}
</style>
