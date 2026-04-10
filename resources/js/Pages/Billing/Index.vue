<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    plans: { type: Object, default: () => ({}) },
    trialDays: { type: Number, default: 7 },
    subscriptionFeatures: { type: Object, default: () => ({}) },
    entitlement: { type: Object, default: null },
});

const isBusy = ref(false);
const activeTier = computed(() => props.subscriptionFeatures?.tier || 'free');
const page = usePage();
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
</script>

<template>
    <Head title="Billing" />

    <AuthenticatedLayout>
        <div class="py-10">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-white">Billing</h1>
                    <p class="mt-2 text-sm text-white/60">
                        Paid plans and trial require Discord-linked accounts.
                    </p>
                </div>

                <div class="mb-6 rounded-xl border border-white/10 bg-surface-800/80 p-4">
                    <p class="text-sm text-white/75">Current tier: <strong class="text-white">{{ String(activeTier).toUpperCase() }}</strong></p>
                    <p class="mt-1 text-xs text-white/55">Free keeps web + Discord alerts and flips filters unlocked.</p>
                </div>

                <div v-if="billingError" class="mb-6 rounded-xl border border-rose-400/30 bg-rose-500/10 p-4 text-sm text-rose-200">
                    {{ billingError }}
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <section class="plan-card">
                        <p class="plan-name">VIP</p>
                        <p class="plan-price">{{ plans?.vip?.price ?? '$4.99' }}/month</p>
                        <ul class="plan-list">
                            <li>Top 3 flips</li>
                            <li>Faster refresh time</li>
                            <li>Priority widget updates</li>
                            <li>VIP tag in leaderboards</li>
                            <li>+2 dashboard slots</li>
                        </ul>
                        <div class="mt-4 flex gap-2">
                            <button class="plan-secondary" :disabled="isBusy || !subscriptionFeatures?.trial_eligible" @click="openTrialConfirm('vip')">Start {{ trialDays }}d Trial</button>
                            <button class="plan-primary" :disabled="isBusy" @click="checkout('vip')">Subscribe VIP</button>
                        </div>
                    </section>

                    <section class="plan-card">
                        <p class="plan-name">MVP</p>
                        <p class="plan-price">{{ plans?.mvp?.price ?? '$8.99' }}/month</p>
                        <ul class="plan-list">
                            <li>Everything from VIP</li>
                            <li>AI-controlled flips section</li>
                            <li>AI trust score and risk signals</li>
                            <li>MVP tag in leaderboards</li>
                        </ul>
                        <div class="mt-4 flex gap-2">
                            <button class="plan-secondary" :disabled="isBusy || !subscriptionFeatures?.trial_eligible" @click="openTrialConfirm('mvp')">Start {{ trialDays }}d Trial</button>
                            <button class="plan-primary" :disabled="isBusy" @click="checkout('mvp')">Subscribe MVP</button>
                        </div>
                    </section>
                </div>

                <div v-if="showTrialConfirmModal" class="modal-backdrop" @click.self="showTrialConfirmModal = false">
                    <div class="modal-card">
                        <h3 class="modal-title">Confirm {{ String(selectedTrialTier).toUpperCase() }} {{ trialDays }}d trial</h3>
                        <p class="modal-copy">Do you really want to activate this trial now? Trial can be used only once per account.</p>
                        <div class="modal-actions">
                            <button class="plan-secondary" :disabled="isBusy" @click="showTrialConfirmModal = false">No</button>
                            <button class="plan-primary" :disabled="isBusy" @click="confirmTrialStart">Yes, activate trial</button>
                        </div>
                    </div>
                </div>

                <div v-if="showTrialSuccessModal" class="modal-backdrop" @click.self="showTrialSuccessModal = false">
                    <div class="modal-card">
                        <h3 class="modal-title">Trial activated</h3>
                        <p class="modal-copy">
                            You now have {{ String(trialActivatedTier).toUpperCase() }} trial access. You can use additional dashboard slots, faster refresh,
                            priority widget updates, and top 3 flips{{ trialActivatedTier === 'mvp' ? ', including AI trust score panel' : '' }}.
                        </p>
                        <div class="modal-actions">
                            <button class="plan-primary" @click="showTrialSuccessModal = false">Great</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.plan-card {
    border-radius: 14px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(15, 20, 28, 0.82);
    padding: 18px;
}

.plan-name {
    font-size: 11px;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: rgba(148, 163, 184, 0.95);
    font-weight: 700;
}

.plan-price {
    margin-top: 8px;
    font-size: 28px;
    font-weight: 700;
    color: #fff;
}

.plan-list {
    margin-top: 12px;
    display: grid;
    gap: 6px;
    color: rgba(255, 255, 255, 0.78);
    font-size: 13px;
}

.plan-primary,
.plan-secondary {
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    padding: 8px 12px;
    font-size: 12px;
    font-weight: 600;
}

.plan-primary {
    background: rgba(16, 185, 129, 0.2);
    border-color: rgba(16, 185, 129, 0.45);
    color: #b7fbd6;
}

.plan-secondary {
    background: rgba(96, 165, 250, 0.18);
    border-color: rgba(96, 165, 250, 0.4);
    color: #bfdbfe;
}

.plan-primary:disabled,
.plan-secondary:disabled {
    opacity: 0.45;
    cursor: not-allowed;
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
</style>
