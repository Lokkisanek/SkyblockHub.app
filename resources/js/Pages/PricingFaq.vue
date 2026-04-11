<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, tm, rt } = useI18n();

const openFaq = ref(null);

function toggleFaq(index) {
    openFaq.value = openFaq.value === index ? null : index;
}

const faqs = computed(() => {
    const values = tm('pricingFaq.faqs');
    if (!Array.isArray(values)) return [];
    return values.map((faq) => ({
        q: rt(faq.q),
        a: rt(faq.a),
    }));
});
</script>

<template>
    <Head :title="t('pricingFaq.title')" />

    <AuthenticatedLayout>
        <div class="py-10">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="mb-8 text-center">
                    <h1 class="text-3xl font-bold text-white sm:text-4xl">{{ t('pricingFaq.heading') }}</h1>
                    <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-white/50">
                        {{ t('pricingFaq.subtitle') }}
                    </p>
                </div>

                <!-- Comparison Table -->
                <div class="overflow-hidden rounded-2xl border border-white/10 bg-surface-900/75 shadow-[0_16px_40px_rgba(0,0,0,0.25)] backdrop-blur-sm">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-white/10">
                                <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest text-white/40">{{ t('pricingFaq.feature') }}</th>
                                <th class="px-5 py-4 text-center text-xs font-bold uppercase tracking-widest text-white/40">{{ t('pricingFaq.free') }}</th>
                                <th class="px-5 py-4 text-center text-xs font-bold uppercase tracking-widest text-emerald-400/80">VIP</th>
                                <th class="px-5 py-4 text-center text-xs font-bold uppercase tracking-widest text-amber-400/80">MVP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.06]">
                            <tr>
                                <td class="px-5 py-3 text-white/75">{{ t('pricingFaq.price') }}</td>
                                <td class="px-5 py-3 text-center font-semibold text-white">{{ t('pricingFaq.free') }}</td>
                                <td class="px-5 py-3 text-center font-semibold text-emerald-400">$4.99/mo</td>
                                <td class="px-5 py-3 text-center font-semibold text-amber-400">$8.99/mo</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">{{ t('pricingFaq.bazaarFlipTable') }}</td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">{{ t('pricingFaq.npcArbitrage') }}</td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">{{ t('pricingFaq.profileStatsBrowser') }}</td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">{{ t('pricingFaq.eventTimerNotifications') }}</td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">{{ t('pricingFaq.mayorIntelPerks') }}</td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">{{ t('pricingFaq.dashboardSlots') }}</td>
                                <td class="check-cell"><span class="text-white/60">1</span></td>
                                <td class="check-cell"><span class="text-emerald-400">3</span></td>
                                <td class="check-cell"><span class="text-amber-400">3</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">{{ t('pricingFaq.top3Flips') }}</td>
                                <td class="check-cell"><span class="check-no">—</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">{{ t('pricingFaq.fasterDataRefresh') }}</td>
                                <td class="check-cell"><span class="check-no">—</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">{{ t('pricingFaq.priorityWidgetUpdates') }}</td>
                                <td class="check-cell"><span class="check-no">—</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">{{ t('pricingFaq.leaderboardTierTag') }}</td>
                                <td class="check-cell"><span class="text-white/40">{{ t('pricingFaq.free') }}</span></td>
                                <td class="check-cell"><span class="text-emerald-400">VIP</span></td>
                                <td class="check-cell"><span class="text-amber-400">MVP</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">{{ t('pricingFaq.aiControlledFlips') }}</td>
                                <td class="check-cell"><span class="check-no">—</span></td>
                                <td class="check-cell"><span class="check-no">—</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">{{ t('pricingFaq.aiTrustSignals') }}</td>
                                <td class="check-cell"><span class="check-no">—</span></td>
                                <td class="check-cell"><span class="check-no">—</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">{{ t('pricingFaq.trial7days') }}</td>
                                <td class="check-cell"><span class="check-no">—</span></td>
                                <td class="check-cell"><span class="check-no">—</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- FAQ -->
                <div class="mt-12">
                    <h2 class="mb-6 text-center text-2xl font-bold text-white">{{ t('pricingFaq.faqTitle') }}</h2>

                    <div class="mx-auto max-w-3xl space-y-2">
                        <div
                            v-for="(faq, i) in faqs"
                            :key="i"
                            class="rounded-xl border transition"
                            :class="openFaq === i ? 'border-white/15 bg-surface-800/80' : 'border-white/[0.06] bg-surface-900/50'"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center justify-between px-5 py-4 text-left"
                                @click="toggleFaq(i)"
                            >
                                <span class="pr-4 text-sm font-semibold text-white">{{ faq.q }}</span>
                                <svg
                                    class="h-4 w-4 shrink-0 text-white/40 transition-transform duration-200"
                                    :class="{ 'rotate-180': openFaq === i }"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div v-if="openFaq === i" class="px-5 pb-4">
                                <p class="text-sm leading-relaxed text-white/60">{{ faq.a }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom CTA -->
                <div class="mt-10 text-center">
                    <p class="text-sm text-white/40">{{ t('pricingFaq.readyUpgrade') }}</p>
                    <Link
                        :href="route('billing')"
                        class="mt-3 inline-flex items-center gap-2 rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-6 py-3 text-sm font-semibold text-emerald-400 transition hover:bg-emerald-500/20 hover:text-emerald-300"
                    >
                        {{ t('pricingFaq.goToBilling') }}
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.check-cell {
    padding: 12px 20px;
    text-align: center;
}

.check-yes {
    color: #34d399;
    font-weight: 600;
}

.check-no {
    color: rgba(255, 255, 255, 0.2);
}
</style>
