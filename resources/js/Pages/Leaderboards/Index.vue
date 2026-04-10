<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Leaderboards" />

    <AuthenticatedLayout>
        <div class="py-10">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="leaderboards-card">
                    <p class="leaderboards-kicker">Community</p>
                    <h1 class="leaderboards-title">Leaderboards</h1>
                    <p class="leaderboards-copy">VIP and MVP users are highlighted with tier tags in ranking.</p>

                    <div class="mt-6 overflow-x-auto">
                        <table class="leaderboard-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Player</th>
                                    <th>Karma</th>
                                    <th>Tier</th>
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
                                        <span v-else class="tier-tag tier-tag--free">FREE</span>
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
