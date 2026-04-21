<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <Head :title="t('about.title')" />

    <AuthenticatedLayout>
        <div class="pt-14 pb-20 sm:pt-16 sm:pb-24">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">

                <!-- Header -->
                <div class="mb-12">
                    <h1 class="text-4xl font-bold tracking-tight text-white sm:text-5xl">{{ t('about.title') }}</h1>
                    <p class="mt-2 text-lg text-white/40">{{ t('about.subtitle') }}</p>
                </div>

                <!-- Row 1: Platform (main 4/6) + Open Source (sidebar 2/6) -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-6">

                    <div class="about-card md:col-span-4" style="border-left: 2px solid rgba(16, 185, 129, 0.35);">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-emerald-400/80">{{ t('about.platformKicker') }}</span>
                        <h2 class="mt-3 text-xl font-semibold text-white">{{ t('about.platformTitle') }}</h2>
                        <p class="mt-3 text-[14px] leading-relaxed text-white/55">
                            {{ t('about.platformDesc') }}
                        </p>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <span class="stat-chip">{{ t('welcome.footer.bazaarFlips') }}</span>
                            <span class="stat-chip">{{ t('welcome.footer.npcArbitrage') }}</span>
                            <span class="stat-chip">{{ t('welcome.footer.eventTimer') }}</span>
                            <span class="stat-chip">{{ t('welcome.footer.mayorIntel') }}</span>
                            <span class="stat-chip">{{ t('welcome.footer.profileStats') }}</span>
                        </div>
                    </div>

                    <div class="about-card md:col-span-2" style="border-left: 2px solid rgba(99, 102, 241, 0.35);">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-indigo-400/80">{{ t('about.openSourceKicker') }}</span>
                        <h2 class="mt-3 text-lg font-semibold text-white">{{ t('about.openSourceTitle') }}</h2>
                        <p class="mt-3 text-[14px] leading-relaxed text-white/55">
                            {{ t('about.openSourceDesc') }}
                        </p>
                        <a
                            href="https://github.com/Lokkisanek/SkyblockHub.play"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-5 inline-flex items-center gap-2 text-sm font-medium text-white/40 transition hover:text-white"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" /></svg>
                            {{ t('about.viewOnGithub') }}
                        </a>
                    </div>

                    <!-- Row 2: Developer (twin 3/6) + Support (twin 3/6) -->

                    <div class="about-card md:col-span-3" style="border-left: 2px solid rgba(16, 185, 129, 0.35);">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-emerald-400/80">{{ t('about.devKicker') }}</span>
                        <h2 class="mt-3 text-lg font-semibold text-white">{{ t('about.devName') }}</h2>
                        <p class="mt-1 text-xs text-white/35">{{ t('about.devLocation') }}</p>
                        <p class="mt-3 text-[14px] leading-relaxed text-white/55">
                            {{ t('about.devBio') }}
                        </p>
                        <a
                            href="https://matyas-odehnal.vercel.app/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-5 inline-flex items-center gap-1.5 text-sm font-medium text-white/40 transition hover:text-white"
                        >
                            matyas-odehnal.vercel.app
                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.22 14.78a.75.75 0 001.06 0l7.22-7.22v5.69a.75.75 0 001.5 0v-7.5a.75.75 0 00-.75-.75h-7.5a.75.75 0 000 1.5h5.69l-7.22 7.22a.75.75 0 000 1.06z" clip-rule="evenodd" /></svg>
                        </a>
                    </div>

                    <div class="about-card md:col-span-3" style="border-left: 2px solid rgba(251, 191, 36, 0.35);">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-amber-400/80">{{ t('about.supportKicker') }}</span>
                        <h2 class="mt-3 text-lg font-semibold text-white">{{ t('about.supportTitle') }}</h2>
                        <p class="mt-3 text-[14px] leading-relaxed text-white/55">
                            {{ t('about.supportDesc') }}
                        </p>
                        <a
                            href="https://buymeacoffee.com/lokkisan"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-5 inline-flex items-center gap-2 rounded-lg border border-amber-400/15 bg-amber-400/[0.07] px-4 py-2 text-[13px] font-medium text-amber-300/80 transition hover:bg-amber-400/15 hover:text-amber-200"
                        >
                            {{ t('about.supportPatreon') }}
                        </a>
                    </div>

                </div>

                <!-- Contact (standalone, full width) -->
                <div class="about-card mt-4" style="border-left: 2px solid rgba(99, 102, 241, 0.35);">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-indigo-400/80">{{ t('about.contactKicker') }}</span>
                    <h2 class="mt-3 text-lg font-semibold text-white">{{ t('about.contactTitle') }}</h2>
                    <p class="mt-3 text-[14px] leading-relaxed text-white/55">
                        {{ t('about.contactDesc') }}
                    </p>
                    <div class="mt-5 flex flex-wrap gap-4">
                        <a href="mailto:odehnalm.08@spst.eu" class="inline-flex items-center gap-2 text-sm font-medium text-white/40 transition hover:text-white">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" /><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" /></svg>
                            odehnalm.08@spst.eu
                        </a>
                        <a href="https://github.com/Lokkisanek" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-sm font-medium text-white/40 transition hover:text-white">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" /></svg>
                            {{ t('about.contactGithub') }}
                        </a>
                        <a href="https://matyas-odehnal.vercel.app/" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-sm font-medium text-white/40 transition hover:text-white">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM4.332 8.027a6.012 6.012 0 011.912-2.706C6.512 5.73 6.974 6 7.5 6A1.5 1.5 0 019 7.5V8a2 2 0 004 0 2 2 0 012-2h.064A6.016 6.016 0 0116.667 8H15a3 3 0 00-3 3v.586l-1.293 1.293A1 1 0 0010 13.414V16.5a5.985 5.985 0 01-5.668-8.473z" clip-rule="evenodd" /></svg>
                            {{ t('about.contactPortfolio') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.about-card {
    background: rgba(15, 18, 25, 0.65);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.04);
    border-radius: 0.75rem;
    padding: 1.25rem 1.5rem;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
    transition: transform 160ms ease, border-color 160ms ease, background 160ms ease;
}

.about-card:hover {
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.1);
    background: rgba(20, 24, 33, 0.8);
}

.stat-chip {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    font-size: 11px;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.45);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.03);
}
</style>
