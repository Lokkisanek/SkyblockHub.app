<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
    subscriptionFeatures: {
        type: Object,
        default: () => ({}),
    },
});

const viewerTier = computed(() => String(props.subscriptionFeatures?.tier || 'free'));
const showUpsell = computed(() => viewerTier.value !== 'mvp');
</script>

<template>
    <Head :title="t('leaderboards.title')" />

    <AuthenticatedLayout>
        <div class="py-10">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="leaderboards-card">
                    <p class="leaderboards-kicker">{{ t('leaderboards.kicker') }}</p>
                    <h1 class="leaderboards-title">{{ t('leaderboards.title') }}</h1>
                    <p class="leaderboards-copy">{{ t('leaderboards.copy') }}</p>

                    <div v-if="showUpsell" class="leaderboards-upsell">
                        <div>
                            <p class="leaderboards-upsell-kicker">{{ t('leaderboards.upsellKicker') }}</p>
                            <p class="leaderboards-upsell-copy">{{ viewerTier === 'free' ? t('leaderboards.upsellFreeCopy') : t('leaderboards.upsellVipCopy') }}</p>
                        </div>
                        <div class="leaderboards-upsell-actions">
                            <Link :href="route('billing')" class="leaderboards-upsell-primary">
                                {{ viewerTier === 'free' ? t('leaderboards.upsellFreeCta') : t('leaderboards.upsellVipCta') }}
                            </Link>
                            <Link :href="`${route('billing')}#faq`" class="leaderboards-upsell-secondary">{{ t('leaderboards.upsellCompare') }}</Link>
                        </div>
                    </div>

                    <div class="mt-6 overflow-x-auto">
                        <table class="leaderboard-table">
                            <thead>
                                <tr>
                                    <th>{{ t('leaderboards.rank') }}</th>
                                    <th>{{ t('leaderboards.player') }}</th>
                                    <th>{{ t('leaderboards.karma') }}</th>
                                    <th>{{ t('leaderboards.tier') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in rows" :key="row.rank">
                                    <td>#{{ row.rank }}</td>
                                    <td>{{ row.display_name }}</td>
                                    <td>{{ row.karma_score }}</td>
                                    <td>
                                        <span v-if="row.tier_tag" class="tier-tag" :class="row.tier_tag === 'MVP' ? 'tier-tag--mvp' : 'tier-tag--vip'">
                                            {{ row.tier_tag }}
                                        </span>
                                        <span v-else class="tier-tag tier-tag--free">{{ t('leaderboards.free') }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.leaderboards-card {
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(15, 20, 28, 0.72);
    backdrop-filter: blur(8px);
    padding: 24px;
}

.leaderboards-kicker {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: rgba(148, 163, 184, 0.9);
}

.leaderboards-title {
    margin-top: 10px;
    font-size: 34px;
    font-weight: 700;
    color: #fff;
}

.leaderboards-copy {
    margin-top: 12px;
    max-width: 70ch;
    font-size: 14px;
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.65);
}

.leaderboards-upsell {
    margin-top: 16px;
    border-radius: 14px;
    border: 1px solid rgba(125, 211, 252, 0.35);
    background: radial-gradient(circle at top, rgba(56, 189, 248, 0.2), rgba(15, 20, 28, 0.85));
    padding: 14px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
}

.leaderboards-upsell-kicker {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: rgba(186, 230, 253, 0.9);
}

.leaderboards-upsell-copy {
    margin-top: 4px;
    font-size: 13px;
    color: rgba(224, 242, 254, 0.88);
}

.leaderboards-upsell-actions {
    display: flex;
    gap: 8px;
}

.leaderboards-upsell-primary,
.leaderboards-upsell-secondary {
    border-radius: 10px;
    padding: 7px 12px;
    font-size: 11px;
    font-weight: 700;
    text-decoration: none;
}

.leaderboards-upsell-primary {
    background: rgba(56, 189, 248, 1);
    color: #0b1120;
}

.leaderboards-upsell-secondary {
    border: 1px solid rgba(186, 230, 253, 0.35);
    color: rgba(224, 242, 254, 0.92);
    background: rgba(0, 0, 0, 0.2);
}

.leaderboards-note {
    margin-top: 16px;
    display: inline-flex;
    border-radius: 999px;
    border: 1px solid rgba(16, 185, 129, 0.35);
    background: rgba(16, 185, 129, 0.1);
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    color: #bef7d4;
}

.leaderboard-table {
    width: 100%;
    border-collapse: collapse;
    color: rgba(255, 255, 255, 0.85);
    font-size: 13px;
}

.leaderboard-table th,
.leaderboard-table td {
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding: 10px 8px;
    text-align: left;
}

.leaderboard-table th {
    color: rgba(203, 213, 225, 0.9);
    font-size: 11px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.tier-tag {
    display: inline-flex;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    padding: 2px 8px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
}

.tier-tag--vip {
    color: #93c5fd;
    border-color: rgba(96, 165, 250, 0.4);
    background: rgba(96, 165, 250, 0.12);
}

.tier-tag--mvp {
    color: #fcd34d;
    border-color: rgba(251, 191, 36, 0.45);
    background: rgba(251, 191, 36, 0.12);
}

.tier-tag--free {
    color: rgba(203, 213, 225, 0.8);
}
</style>
