<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    operations: { type: Object, default: () => ({}) },
});

const hypixel = ref({ ...(props.operations.hypixel || {}) });
const refreshing = ref(false);

const numberFmt = (value) => new Intl.NumberFormat('en-US').format(Number(value || 0));

const hypixelStatusClass = computed(() => {
    const map = {
        ok: 'admin-ops-hypixel--ok',
        throttled: 'admin-ops-hypixel--warn',
        no_key: 'admin-ops-hypixel--error',
        error: 'admin-ops-hypixel--error',
    };
    return map[hypixel.value?.status] || '';
});

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function refreshHypixel() {
    refreshing.value = true;
    try {
        const res = await fetch(route('admin.operations.refresh-hypixel'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: '{}',
        });
        const data = await res.json();
        if (data.hypixel) {
            hypixel.value = data.hypixel;
        }
    } finally {
        refreshing.value = false;
    }
}

function formatDate(value) {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
}
</script>

<template>
    <section class="panel-card admin-ops-panel">
        <header class="panel-head">
            <h2>Operations</h2>
            <p>API health, cached profiles, leaderboard, and background jobs.</p>
        </header>

        <article class="admin-ops-hypixel" :class="hypixelStatusClass">
            <div class="admin-ops-hypixel__top">
                <div>
                    <p class="admin-ops-kicker">Hypixel API</p>
                    <p class="admin-ops-hypixel__label">{{ hypixel.label || 'Unknown' }}</p>
                </div>
                <span class="admin-ops-pill">{{ hypixel.status || 'unknown' }}</span>
            </div>
            <p class="admin-ops-hypixel__message">{{ hypixel.message }}</p>
            <p v-if="hypixel.player_count != null" class="admin-ops-hypixel__meta">
                Network players: {{ numberFmt(hypixel.player_count) }}
                <span v-if="hypixel.checked_at"> · checked {{ formatDate(hypixel.checked_at) }}</span>
            </p>
            <button type="button" class="admin-ops-btn" :disabled="refreshing" @click="refreshHypixel">
                {{ refreshing ? 'Checking…' : 'Re-check API' }}
            </button>
        </article>

        <div class="admin-ops-grid">
            <article class="admin-ops-card">
                <p class="admin-ops-kicker">Profiles cache</p>
                <p class="admin-ops-value">{{ numberFmt(operations.profiles?.total) }}</p>
                <ul class="admin-ops-list">
                    <li><span>Selected</span><strong>{{ numberFmt(operations.profiles?.selected_profiles) }}</strong></li>
                    <li><span>Stale (&gt;{{ operations.profiles?.stale_after_days }}d)</span><strong>{{ numberFmt(operations.profiles?.stale) }}</strong></li>
                    <li><span>Last fetch</span><strong>{{ formatDate(operations.profiles?.latest_fetched_at) }}</strong></li>
                </ul>
            </article>

            <article class="admin-ops-card">
                <p class="admin-ops-kicker">Leaderboard snapshot</p>
                <p class="admin-ops-value">{{ numberFmt(operations.leaderboard?.rows) }}</p>
                <ul class="admin-ops-list">
                    <li><span>Table</span><strong>{{ operations.leaderboard?.table || '—' }}</strong></li>
                    <li><span>Data refreshed</span><strong>{{ formatDate(operations.leaderboard?.slice_max_fetched_at) }}</strong></li>
                </ul>
                <p class="admin-ops-hint">Run: <code>php artisan leaderboard:rebuild-snapshot</code></p>
            </article>

            <article class="admin-ops-card">
                <p class="admin-ops-kicker">Users</p>
                <p class="admin-ops-value">{{ numberFmt(operations.users?.total) }}</p>
                <ul class="admin-ops-list">
                    <li><span>Discord linked</span><strong>{{ numberFmt(operations.users?.discord_linked) }}</strong></li>
                    <li><span>MC linked</span><strong>{{ numberFmt(operations.users?.minecraft_linked) }}</strong></li>
                    <li><span>VIP tier</span><strong>{{ numberFmt(operations.users?.vip_ranked) }}</strong></li>
                    <li><span>Donators</span><strong>{{ numberFmt(operations.users?.donators) }}</strong></li>
                </ul>
            </article>

            <article class="admin-ops-card">
                <p class="admin-ops-kicker">Bazaar &amp; queue</p>
                <ul class="admin-ops-list admin-ops-list--spaced">
                    <li><span>Bazaar products</span><strong>{{ numberFmt(operations.bazaar?.products) }}</strong></li>
                    <li><span>Bazaar updated</span><strong>{{ formatDate(operations.bazaar?.latest_updated_at) }}</strong></li>
                    <li><span>Failed jobs</span><strong>{{ numberFmt(operations.queue?.failed_jobs) }}</strong></li>
                </ul>
                <ul class="admin-ops-list admin-ops-list--spaced">
                    <li><span>Scheduled ingest</span><strong>{{ operations.ingest?.enabled ? 'On' : 'Off' }}</strong></li>
                    <li><span>Ingest / run</span><strong>{{ numberFmt(operations.ingest?.max_per_run) }}</strong></li>
                    <li><span>Guild crawl (cron)</span><strong>{{ operations.ingest?.guild_crawl_enabled ? 'On' : 'Off' }}</strong></li>
                </ul>
            </article>
        </div>
    </section>
</template>

<style scoped>
.admin-ops-panel {
    margin-top: 1.5rem;
}

.admin-ops-kicker {
    margin: 0;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgba(148, 163, 184, 0.95);
}

.admin-ops-hypixel {
    margin-top: 1rem;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.25);
    background: rgba(15, 23, 42, 0.55);
    padding: 1rem;
}

.admin-ops-hypixel--ok {
    border-color: rgba(52, 211, 153, 0.4);
    background: rgba(52, 211, 153, 0.08);
}

.admin-ops-hypixel--warn {
    border-color: rgba(245, 158, 11, 0.45);
    background: rgba(245, 158, 11, 0.08);
}

.admin-ops-hypixel--error {
    border-color: rgba(248, 113, 113, 0.45);
    background: rgba(248, 113, 113, 0.08);
}

.admin-ops-hypixel__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}

.admin-ops-hypixel__label {
    margin: 0.25rem 0 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: #f8fafc;
}

.admin-ops-pill {
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    border: 1px solid rgba(148, 163, 184, 0.35);
    color: rgba(226, 232, 240, 0.9);
}

.admin-ops-hypixel__message {
    margin: 0.65rem 0 0;
    font-size: 0.84rem;
    color: rgba(226, 232, 240, 0.88);
}

.admin-ops-hypixel__meta {
    margin: 0.35rem 0 0;
    font-size: 0.75rem;
    color: rgba(148, 163, 184, 0.9);
}

.admin-ops-btn {
    margin-top: 0.75rem;
    border-radius: 8px;
    border: 1px solid rgba(148, 163, 184, 0.28);
    background: rgba(2, 6, 23, 0.55);
    color: #e2e8f0;
    padding: 0.4rem 0.75rem;
    font-size: 0.78rem;
    font-weight: 600;
}

.admin-ops-btn:hover:not(:disabled) {
    border-color: rgba(11, 202, 81, 0.45);
    color: #bbf7d0;
}

.admin-ops-btn:disabled {
    opacity: 0.5;
}

.admin-ops-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
    margin-top: 1rem;
}

@media (min-width: 1100px) {
    .admin-ops-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

.admin-ops-card {
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: rgba(2, 6, 23, 0.45);
    padding: 0.85rem;
    min-width: 0;
}

.admin-ops-value {
    margin: 0.35rem 0 0.55rem;
    font-size: 1.35rem;
    font-weight: 800;
    color: #fff;
    font-variant-numeric: tabular-nums;
}

.admin-ops-list {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 0.35rem;
}

.admin-ops-list--spaced {
    margin-top: 0.5rem;
}

.admin-ops-list li {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.74rem;
    color: rgba(203, 213, 225, 0.88);
}

.admin-ops-list strong {
    color: #f8fafc;
    font-weight: 600;
    text-align: right;
}

.admin-ops-hint {
    margin: 0.55rem 0 0;
    font-size: 0.68rem;
    color: rgba(148, 163, 184, 0.85);
}

.admin-ops-hint code {
    font-size: 0.65rem;
    color: rgba(226, 232, 240, 0.9);
}
</style>
