<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <Head :title="t('termsPage.title')" />

    <AuthenticatedLayout>
        <div class="pt-14 pb-20 sm:pt-16 sm:pb-24">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">

                <h1 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ t('termsPage.heading') }}</h1>
                <p class="mt-2 text-sm text-white/40">{{ t('termsPage.lastUpdated') }}</p>

                <div class="legal-content mt-10 space-y-8">

                    <section>
                        <h2>{{ t('termsPage.section1Title') }}</h2>
                        <p>{{ t('termsPage.section1Text') }}</p>
                    </section>

                    <section>
                        <h2>{{ t('termsPage.section2Title') }}</h2>
                        <p>{{ t('termsPage.section2Text') }}</p>
                    </section>

                    <section>
                        <h2>{{ t('termsPage.section3Title') }}</h2>
                        <ul>
                            <li>{{ t('termsPage.section3Item1') }}</li>
                            <li>{{ t('termsPage.section3Item2') }}</li>
                            <li>{{ t('termsPage.section3Item3') }}</li>
                        </ul>
                    </section>

                    <section>
                        <h2>{{ t('termsPage.section4Title') }}</h2>
                        <p>{{ t('termsPage.section4Intro') }}</p>
                        <ul>
                            <li>{{ t('termsPage.section4Item1') }}</li>
                            <li>{{ t('termsPage.section4Item2') }}</li>
                            <li>{{ t('termsPage.section4Item3') }}</li>
                            <li>{{ t('termsPage.section4Item4') }}</li>
                            <li>{{ t('termsPage.section4Item5') }}</li>
                        </ul>
                    </section>

                    <section>
                        <h2>{{ t('termsPage.section5Title') }}</h2>
                        <p>{{ t('termsPage.section5Text') }}</p>
                    </section>

                    <section>
                        <h2>{{ t('termsPage.section6Title') }}</h2>
                        <p>{{ t('termsPage.section6Text') }}</p>
                    </section>

                    <section>
                        <h2>{{ t('termsPage.section7Title') }}</h2>
                        <p>{{ t('termsPage.section7Text') }}</p>
                    </section>

                    <section>
                        <h2>{{ t('termsPage.section8Title') }}</h2>
                        <p>{{ t('termsPage.section8Text') }}</p>
                    </section>

                    <section>
                        <h2>{{ t('termsPage.section9Title') }}</h2>
                        <p>{{ t('termsPage.section9Text') }}</p>
                    </section>

                    <section>
                        <h2>{{ t('termsPage.section10Title') }}</h2>
                        <p>{{ t('termsPage.section10Text') }} <a href="mailto:odehnalm.08@spst.eu" class="text-emerald-400/80 hover:text-emerald-300">odehnalm.08@spst.eu</a>.</p>
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
