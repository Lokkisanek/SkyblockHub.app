<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, tm, rt } = useI18n();

const props = defineProps({
    plans: { type: Object, default: () => ({}) },
    trialDays: { type: Number, default: 7 },
    subscriptionFeatures: { type: Object, default: () => ({}) },
    entitlement: { type: Object, default: null },
});

const isBusy = ref(false);
const activeTier = computed(() => props.subscriptionFeatures?.tier || 'free');
const page = usePage();
const isTestingAdmin = computed(() => Boolean(page.props.auth?.testing_admin));
const hasActiveEntitlement = computed(() => Boolean(props.subscriptionFeatures?.has_active_entitlement));
const showTrialConfirmModal = ref(false);
const showTrialSuccessModal = ref(false);
const selectedTrialTier = ref('vip');
const trialActivatedTier = ref('vip');
const billingError = computed(() => page.props.errors?.billing || '');

function openTrialConfirm(tier) {
    selectedTrialTier.value = tier;
    showTrialConfirmModal.value = true;
}

function confirmTrialStart() {
    if (isBusy.value) {
        return;
    }

    isBusy.value = true;
    router.post(route('billing.trial'), { tier: selectedTrialTier.value }, {
        preserveScroll: true,
        onSuccess: () => {
            trialActivatedTier.value = selectedTrialTier.value;
            showTrialConfirmModal.value = false;
            showTrialSuccessModal.value = true;
        },
        onFinish: () => {
            isBusy.value = false;
        },
    });
}

function checkout(tier) {
    if (isBusy.value) {
        return;
    }

    isBusy.value = true;
    router.post(route('billing.checkout'), { tier }, {
        preserveScroll: true,
        onFinish: () => {
            isBusy.value = false;
        },
    });
}

function toggleDevSubscription() {
    if (isBusy.value || !isTestingAdmin.value) {
        return;
    }

    isBusy.value = true;
    router.post(route('billing.dev-toggle-subscription'), {}, {
        preserveScroll: true,
        onFinish: () => {
            isBusy.value = false;
        },
    });
}
</script>

<template>
    <Head :title="t('billing.title')" />

    <AuthenticatedLayout>
        <div class="py-10">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-white">{{ t('billing.title') }}</h1>
                    <p class="mt-2 text-sm text-white/60">
                        {{ t('billing.discordRequired') }}
                    </p>
                </div>

                <div class="mb-6 rounded-xl border border-white/10 bg-surface-800/80 p-4">
                    <p class="text-sm text-white/75">{{ t('billing.currentTier') }} <strong class="text-white">{{ String(activeTier).toUpperCase() }}</strong></p>
                    <p class="mt-1 text-xs text-white/55">{{ t('billing.freeDesc') }}</p>
                </div>

                <div v-if="billingError" class="mb-6 rounded-xl border border-rose-400/30 bg-rose-500/10 p-4 text-sm text-rose-200">
                    {{ billingError }}
                </div>

                <div v-if="isTestingAdmin" class="mb-6 rounded-xl border border-cyan-400/30 bg-cyan-500/10 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-cyan-200/85">Dev Admin</p>
                    <p class="mt-1 text-sm text-cyan-100/85">
                        Subscription state: <strong>{{ hasActiveEntitlement ? 'ON' : 'OFF' }}</strong>
                    </p>
                    <button class="mt-3 dev-toggle-btn" :disabled="isBusy" @click="toggleDevSubscription">
                        {{ hasActiveEntitlement ? 'Disable subscription' : 'Enable subscription (VIP)' }}
                    </button>
                </div>

                <div class="overflow-hidden rounded-2xl border border-white/10 bg-surface-900/75 shadow-[0_16px_40px_rgba(0,0,0,0.25)] backdrop-blur-sm">
                    <table class="w-full table-fixed text-left text-sm">
                        <colgroup>
                            <col class="feature-col" />
                            <col class="plan-col" />
                            <col class="plan-col" />
                            <col class="plan-col" />
                        </colgroup>
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
                        <tfoot>
                            <tr class="border-t border-white/10" v-if="subscriptionFeatures?.trial_eligible">
                                <td class="px-5 py-4 text-xs text-white/45">Activate free trial</td>
                                <td class="px-5 py-4 text-center">
                                    <span class="text-[11px] font-semibold uppercase tracking-wider text-white/30">No trial</span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <button class="trial-btn" :disabled="isBusy" @click="openTrialConfirm('vip')">
                                        {{ t('billing.startTrial', { days: trialDays }) }}
                                    </button>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <button class="trial-btn" :disabled="isBusy" @click="openTrialConfirm('mvp')">
                                        {{ t('billing.startTrial', { days: trialDays }) }}
                                    </button>
                                </td>
                            </tr>
                            <tr class="border-t border-white/10">
                                <td class="px-5 py-4"></td>
                                <td class="px-5 py-4 text-center">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-white/30">{{ t('pricingFaq.free') }}</span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <button class="buy-btn buy-btn--vip" :disabled="isBusy" @click="checkout('vip')">
                                        <svg class="buy-btn__cart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                        BUY
                                    </button>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <button class="buy-btn buy-btn--mvp" :disabled="isBusy" @click="checkout('mvp')">
                                        <svg class="buy-btn__cart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                        BUY
                                    </button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <p class="mt-4 text-center text-xs text-white/35">
                    <Link :href="route('pricing')" class="underline decoration-white/20 underline-offset-2 transition hover:text-white/60">{{ t('billing.comparePlans') }}</Link>
                </p>

                <div v-if="showTrialConfirmModal" class="modal-backdrop" @click.self="showTrialConfirmModal = false">
                    <div class="modal-card">
                        <h3 class="modal-title">{{ t('billing.confirmTitle', { tier: String(selectedTrialTier).toUpperCase(), days: trialDays }) }}</h3>
                        <p class="modal-copy">{{ t('billing.confirmDesc') }}</p>
                        <div class="modal-actions">
                            <button class="plan-secondary" :disabled="isBusy" @click="showTrialConfirmModal = false">{{ t('billing.confirmNo') }}</button>
                            <button class="modal-btn" :disabled="isBusy" @click="confirmTrialStart">{{ t('billing.confirmYes') }}</button>
                        </div>
                    </div>
                </div>

                <div v-if="showTrialSuccessModal" class="modal-backdrop" @click.self="showTrialSuccessModal = false">
                    <div class="modal-card">
                        <h3 class="modal-title">{{ t('billing.trialActivated') }}</h3>
                        <p class="modal-copy">
                            {{ t('billing.trialSuccess', { tier: String(trialActivatedTier).toUpperCase() }) }}
                        </p>
                        <div class="modal-actions">
                            <button class="modal-btn" @click="showTrialSuccessModal = false">{{ t('billing.great') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.plan-secondary {
    border-radius: 4px;
    border: 2px solid rgba(255, 255, 255, 0.15);
    background: rgba(255, 255, 255, 0.06);
    padding: 8px 16px;
    font-size: 12px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.7);
    transition: all 0.15s ease;
}

.plan-secondary:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

.dev-toggle-btn {
    border-radius: 4px;
    border: 1px solid rgba(34, 211, 238, 0.5);
    background: rgba(6, 182, 212, 0.15);
    padding: 8px 12px;
    font-size: 12px;
    font-weight: 700;
    color: #a5f3fc;
    transition: all 0.15s ease;
}

.dev-toggle-btn:hover:not(:disabled) {
    background: rgba(6, 182, 212, 0.25);
}

.trial-btn {
    border-radius: 4px;
    border: 1px solid rgba(96, 165, 250, 0.45);
    background: rgba(96, 165, 250, 0.14);
    padding: 7px 10px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.03em;
    color: #bfdbfe;
    transition: all 0.15s ease;
}

.trial-btn:hover:not(:disabled) {
    background: rgba(96, 165, 250, 0.24);
}

.buy-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 4px;
    border: 2px solid;
    padding: 8px 20px;
    font-size: 14px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    background: rgba(0, 0, 0, 0.65);
    cursor: pointer;
    transition: all 0.15s ease;
}

.buy-btn__cart {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

.buy-btn--vip {
    border-color: #55ff55;
    color: #55ff55;
    text-shadow: 0 0 8px rgba(85, 255, 85, 0.35);
    box-shadow: 0 0 12px rgba(85, 255, 85, 0.15), inset 0 0 12px rgba(85, 255, 85, 0.06);
}

.buy-btn--vip:hover:not(:disabled) {
    background: rgba(85, 255, 85, 0.12);
    box-shadow: 0 0 20px rgba(85, 255, 85, 0.3), inset 0 0 16px rgba(85, 255, 85, 0.1);
}

.buy-btn--mvp {
    border-color: #55ffff;
    color: #55ffff;
    text-shadow: 0 0 8px rgba(85, 255, 255, 0.35);
    box-shadow: 0 0 12px rgba(85, 255, 255, 0.15), inset 0 0 12px rgba(85, 255, 255, 0.06);
}

.buy-btn--mvp:hover:not(:disabled) {
    background: rgba(85, 255, 255, 0.12);
    box-shadow: 0 0 20px rgba(85, 255, 255, 0.3), inset 0 0 16px rgba(85, 255, 255, 0.1);
}

.buy-btn:disabled,
.plan-secondary:disabled,
.trial-btn:disabled,
.dev-toggle-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

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

.feature-col {
    width: 46%;
}

.plan-col {
    width: 18%;
}

.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 90;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(4, 6, 10, 0.74);
    backdrop-filter: blur(6px);
}

.modal-card {
    width: min(520px, calc(100vw - 28px));
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.15);
    background: rgba(16, 22, 30, 0.96);
    padding: 18px;
}

.modal-title {
    font-size: 18px;
    font-weight: 700;
    color: #fff;
}

.modal-copy {
    margin-top: 10px;
    font-size: 13px;
    line-height: 1.55;
    color: rgba(255, 255, 255, 0.72);
}

.modal-actions {
    margin-top: 14px;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.modal-btn {
    border-radius: 4px;
    border: 2px solid #55ff55;
    background: rgba(0, 0, 0, 0.65);
    padding: 8px 20px;
    font-size: 13px;
    font-weight: 700;
    color: #55ff55;
    cursor: pointer;
    transition: all 0.15s ease;
}

.modal-btn:hover:not(:disabled) {
    background: rgba(85, 255, 85, 0.12);
}

.modal-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
</style>
