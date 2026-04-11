<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const openFaq = ref(null);

function toggleFaq(index) {
    openFaq.value = openFaq.value === index ? null : index;
}

const faqs = [
    {
        q: 'How does the 7-day free trial work?',
        a: 'The MVP plan comes with a one-time 7-day free trial. No credit card is required to start. Once the trial expires, your account reverts to the free tier unless you subscribe. Each Discord account is limited to one trial — this cannot be reset.',
    },
    {
        q: 'Can I switch between VIP and MVP?',
        a: 'Yes. You can upgrade from VIP to MVP at any time — the remaining balance of your current billing cycle is prorated toward the new plan. Downgrading works the same way; the change takes effect at the start of your next billing cycle.',
    },
    {
        q: 'How do I cancel my subscription?',
        a: 'Go to Settings → Subscription and click Cancel. Your paid features stay active until the end of the current billing period. After that, your account reverts to the free tier. No penalty, no questions asked.',
    },
    {
        q: 'What is the refund policy?',
        a: 'All payments are final and non-refundable under normal circumstances. We do not offer refunds for unused time, accidental purchases, or change of mind. Refunds are only considered if SkyblockHub experiences a significant, verified outage lasting more than 7 consecutive days that prevents you from using core paid features. In that case, contact us and we will evaluate a partial credit or refund on a case-by-case basis.',
    },
    {
        q: 'What payment methods are accepted?',
        a: 'We use Stripe for payment processing. You can pay with any major credit or debit card (Visa, Mastercard, Amex, Discover). Some regions may also support Apple Pay, Google Pay, and local payment methods through Stripe.',
    },
    {
        q: 'Is my payment information safe?',
        a: 'We never store your card details. All payment processing is handled by Stripe, a PCI Level 1 certified payment processor — the highest level of security certification in the payments industry.',
    },
    {
        q: 'What happens to my data if I cancel?',
        a: 'Your dashboard layouts, settings, and linked accounts are preserved. If you re-subscribe later, everything will be exactly as you left it. Your data is only deleted if you explicitly delete your account.',
    },
    {
        q: 'Do free users get ads or tracking?',
        a: 'No. SkyblockHub has zero ads, zero tracking cookies, and zero analytics fingerprinting — for all users, free or paid. See our Privacy Policy for details.',
    },
];
</script>

<template>
    <Head title="Pricing & FAQ" />

    <AuthenticatedLayout>
        <div class="py-10">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="mb-8 text-center">
                    <h1 class="text-3xl font-bold text-white sm:text-4xl">Plans &amp; Pricing</h1>
                    <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-white/50">
                        All core modules are free. Paid plans unlock advanced features, faster data, and priority updates.
                    </p>
                </div>

                <!-- Comparison Table -->
                <div class="overflow-hidden rounded-2xl border border-white/10 bg-surface-900/75 shadow-[0_16px_40px_rgba(0,0,0,0.25)] backdrop-blur-sm">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-white/10">
                                <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest text-white/40">Feature</th>
                                <th class="px-5 py-4 text-center text-xs font-bold uppercase tracking-widest text-white/40">Free</th>
                                <th class="px-5 py-4 text-center text-xs font-bold uppercase tracking-widest text-emerald-400/80">VIP</th>
                                <th class="px-5 py-4 text-center text-xs font-bold uppercase tracking-widest text-amber-400/80">MVP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.06]">
                            <tr>
                                <td class="px-5 py-3 text-white/75">Price</td>
                                <td class="px-5 py-3 text-center font-semibold text-white">Free</td>
                                <td class="px-5 py-3 text-center font-semibold text-emerald-400">$4.99/mo</td>
                                <td class="px-5 py-3 text-center font-semibold text-amber-400">$8.99/mo</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">Bazaar flip table</td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">NPC arbitrage</td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">Profile Stats browser</td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">Event Timer + notifications</td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">Mayor intel &amp; perks</td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">Dashboard slots</td>
                                <td class="check-cell"><span class="text-white/60">1</span></td>
                                <td class="check-cell"><span class="text-emerald-400">3</span></td>
                                <td class="check-cell"><span class="text-amber-400">3</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">Top 3 flips highlighted</td>
                                <td class="check-cell"><span class="check-no">—</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">Faster data refresh</td>
                                <td class="check-cell"><span class="check-no">—</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">Priority widget updates</td>
                                <td class="check-cell"><span class="check-no">—</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">Leaderboard tier tag</td>
                                <td class="check-cell"><span class="text-white/40">FREE</span></td>
                                <td class="check-cell"><span class="text-emerald-400">VIP</span></td>
                                <td class="check-cell"><span class="text-amber-400">MVP</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">AI-controlled flips</td>
                                <td class="check-cell"><span class="check-no">—</span></td>
                                <td class="check-cell"><span class="check-no">—</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">AI trust score &amp; risk signals</td>
                                <td class="check-cell"><span class="check-no">—</span></td>
                                <td class="check-cell"><span class="check-no">—</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-white/75">7-day free trial</td>
                                <td class="check-cell"><span class="check-no">—</span></td>
                                <td class="check-cell"><span class="check-no">—</span></td>
                                <td class="check-cell"><span class="check-yes">✓</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- FAQ -->
                <div class="mt-12">
                    <h2 class="mb-6 text-center text-2xl font-bold text-white">Frequently Asked Questions</h2>

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
                    <p class="text-sm text-white/40">Ready to upgrade?</p>
                    <Link
                        :href="route('billing')"
                        class="mt-3 inline-flex items-center gap-2 rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-6 py-3 text-sm font-semibold text-emerald-400 transition hover:bg-emerald-500/20 hover:text-emerald-300"
                    >
                        Go to Billing
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
