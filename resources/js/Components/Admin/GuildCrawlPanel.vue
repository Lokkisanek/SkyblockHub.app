<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    initial: { type: Object, default: () => ({}) },
});

const guildList = ref('');
const maxGuilds = ref(50);
const seedLimit = ref(0);
const memberLimit = ref(5000);
const delayMs = ref(2000);
const newOnly = ref(true);

const status = ref({ ...props.initial });
const busy = ref(false);
const error = ref('');

let pollTimer = null;

const runningStatuses = ['queued', 'discovering', 'ingesting'];

const isRunning = computed(() => runningStatuses.includes(status.value?.status));

const progressPct = computed(() => {
    const total = Number(status.value?.total_members || 0);
    const done = Number(status.value?.processed || 0);
    if (total <= 0) return 0;
    return Math.min(100, Math.round((done / total) * 100));
});

const statusLabel = computed(() => {
    const map = {
        idle: 'Idle',
        queued: 'Queued',
        discovering: 'Discovering guilds',
        ingesting: 'Ingesting profiles',
        completed: 'Completed',
        cancelled: 'Cancelled',
        failed: 'Failed',
    };
    return map[status.value?.status] || status.value?.status || 'Unknown';
});

const statusClass = computed(() => {
    const s = status.value?.status;
    if (s === 'completed') return 'guild-crawl-status--ok';
    if (s === 'failed') return 'guild-crawl-status--error';
    if (s === 'cancelled') return 'guild-crawl-status--warn';
    if (isRunning.value) return 'guild-crawl-status--run';
    return '';
});

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function apiFetch(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            ...(options.headers || {}),
        },
        ...options,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(data.message || `Request failed (${response.status})`);
    }

    return data;
}

async function refreshStatus() {
    try {
        const data = await apiFetch(route('admin.guild-crawl.status'));
        if (data.guild_crawl) {
            status.value = data.guild_crawl;
        }
    } catch (e) {
        // Keep polling quiet on transient errors
        console.warn('Guild crawl status poll failed', e);
    }
}

function startPolling() {
    stopPolling();
    pollTimer = window.setInterval(refreshStatus, 2000);
}

function stopPolling() {
    if (pollTimer !== null) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
}

async function startCrawl() {
    error.value = '';
    busy.value = true;

    try {
        const data = await apiFetch(route('admin.guild-crawl.start'), {
            method: 'POST',
            body: JSON.stringify({
                guild_list: guildList.value,
                max_guilds: maxGuilds.value,
                seed_limit: seedLimit.value,
                member_limit: memberLimit.value,
                delay_ms: delayMs.value,
                new_only: newOnly.value,
            }),
        });
        status.value = data.guild_crawl ?? status.value;
        startPolling();
    } catch (e) {
        error.value = e.message || 'Could not start crawl.';
    } finally {
        busy.value = false;
    }
}

async function cancelCrawl() {
    error.value = '';
    busy.value = true;

    try {
        const data = await apiFetch(route('admin.guild-crawl.cancel'), { method: 'POST', body: '{}' });
        status.value = data.guild_crawl ?? status.value;
    } catch (e) {
        error.value = e.message || 'Could not cancel crawl.';
    } finally {
        busy.value = false;
    }
}

watch(isRunning, (run) => {
    if (run) {
        startPolling();
    } else {
        stopPolling();
    }
});

onMounted(() => {
    if (isRunning.value) {
        startPolling();
    }
});

onBeforeUnmount(stopPolling);
</script>

<template>
    <section class="panel-card guild-crawl-panel">
        <header class="panel-head">
            <h2>Guild profile crawl</h2>
            <p>Fetch Hypixel guild rosters and ingest member SkyBlock profiles (lightweight leaderboard data). Long crawls need a queue worker (<code class="text-white/50">php artisan queue:work</code>).</p>
        </header>

        <div class="guild-crawl-status" :class="statusClass">
            <div class="guild-crawl-status__row">
                <span class="guild-crawl-status__label">Status</span>
                <strong>{{ statusLabel }}</strong>
            </div>
            <p class="guild-crawl-status__message">{{ status.message }}</p>
        </div>

        <div v-if="isRunning || status.processed > 0" class="guild-crawl-progress">
            <div class="guild-crawl-progress__bar">
                <div class="guild-crawl-progress__fill" :style="{ width: progressPct + '%' }" />
            </div>
            <p class="guild-crawl-progress__text">
                <template v-if="status.status === 'discovering'">Calling Hypixel guild API…</template>
                <template v-else-if="status.total_members > 0">
                    {{ status.processed }} / {{ status.total_members }} players
                    <span class="text-white/50">(OK {{ status.ok }}, failed {{ status.failed }})</span>
                </template>
            </p>
        </div>

        <dl v-if="status.guilds_found || status.api_calls" class="guild-crawl-metrics">
            <div><dt>Guilds found</dt><dd>{{ status.guilds_found }}</dd></div>
            <div><dt>Guild names sent</dt><dd>{{ status.guild_names_requested }}</dd></div>
            <div><dt>API calls</dt><dd>{{ status.api_calls }}</dd></div>
        </dl>

        <div v-if="status.guild_lookups?.length" class="guild-crawl-lookups">
            <p class="guild-crawl-lookups__title">Per-guild lookup</p>
            <div class="guild-crawl-lookups__table-wrap">
                <table class="guild-crawl-lookups__table">
                    <thead>
                        <tr>
                            <th>Name sent</th>
                            <th>Result</th>
                            <th>Members</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in status.guild_lookups"
                            :key="row.name"
                            :class="row.ok ? 'guild-crawl-lookups__row--ok' : 'guild-crawl-lookups__row--fail'"
                        >
                            <td>{{ row.name }}</td>
                            <td>
                                <template v-if="row.ok">
                                    OK<span v-if="row.hypixel_name && row.hypixel_name !== row.name"> → {{ row.hypixel_name }}</span>
                                </template>
                                <template v-else>
                                    {{ row.cause === 'no_response' ? 'No response (rate limit?)' : row.cause }}
                                </template>
                            </td>
                            <td>{{ row.ok ? row.members : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="guild-crawl-form">
            <label class="guild-crawl-label">
                Guild names
                            <span class="guild-crawl-hint">One per line or comma-separated — exact Hypixel guild name. Use Delay ≥ 2000 ms for many guilds (rate limit).</span>
                <textarea
                    v-model="guildList"
                    class="guild-crawl-textarea"
                    rows="8"
                    placeholder="My SkyBlock Guild&#10;Another Guild"
                    :disabled="isRunning"
                />
            </label>

            <div class="guild-crawl-options">
                <label>
                    Max guilds
                    <input v-model.number="maxGuilds" type="number" min="1" max="200" :disabled="isRunning" />
                </label>
                <label>
                    Max members
                    <input v-model.number="memberLimit" type="number" min="1" max="25000" :disabled="isRunning" />
                </label>
                <label>
                    Delay (ms)
                    <input v-model.number="delayMs" type="number" min="0" max="15000" step="100" :disabled="isRunning" />
                </label>
                <label>
                    Seed limit
                    <input v-model.number="seedLimit" type="number" min="0" max="500" :disabled="isRunning" />
                </label>
                <label class="guild-crawl-check">
                    <input v-model="newOnly" type="checkbox" :disabled="isRunning" />
                    Only players not in cache yet
                </label>
            </div>

            <p v-if="error" class="guild-crawl-error">{{ error }}</p>

            <div class="guild-crawl-actions">
                <button
                    type="button"
                    class="guild-crawl-btn guild-crawl-btn--primary"
                    :disabled="isRunning || busy || !guildList.trim()"
                    @click="startCrawl"
                >
                    {{ isRunning ? 'Running…' : 'Start fetch' }}
                </button>
                <button
                    type="button"
                    class="guild-crawl-btn"
                    :disabled="!isRunning || busy"
                    @click="cancelCrawl"
                >
                    Cancel
                </button>
                <button type="button" class="guild-crawl-btn guild-crawl-btn--ghost" :disabled="busy" @click="refreshStatus">
                    Refresh status
                </button>
            </div>
        </div>

        <ul v-if="status.recent_log?.length" class="guild-crawl-log">
            <li v-for="(entry, idx) in status.recent_log" :key="idx">
                <time>{{ entry.at }}</time>
                <span>{{ entry.line }}</span>
            </li>
        </ul>
    </section>
</template>

<style scoped>
.guild-crawl-panel {
    margin-top: 1.5rem;
}

.guild-crawl-status {
    margin-top: 1rem;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.22);
    background: rgba(15, 23, 42, 0.55);
    padding: 0.85rem 1rem;
}

.guild-crawl-status--run {
    border-color: rgba(96, 165, 250, 0.45);
    background: rgba(59, 130, 246, 0.1);
}

.guild-crawl-status--ok {
    border-color: rgba(52, 211, 153, 0.4);
    background: rgba(52, 211, 153, 0.08);
}

.guild-crawl-status--warn {
    border-color: rgba(245, 158, 11, 0.4);
    background: rgba(245, 158, 11, 0.08);
}

.guild-crawl-status--error {
    border-color: rgba(248, 113, 113, 0.45);
    background: rgba(248, 113, 113, 0.08);
}

.guild-crawl-status__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.guild-crawl-status__label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: rgba(148, 163, 184, 0.9);
}

.guild-crawl-status__message {
    margin: 0.5rem 0 0;
    font-size: 0.85rem;
    color: rgba(226, 232, 240, 0.88);
}

.guild-crawl-progress {
    margin-top: 1rem;
}

.guild-crawl-progress__bar {
    height: 8px;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.9);
    overflow: hidden;
    border: 1px solid rgba(148, 163, 184, 0.2);
}

.guild-crawl-progress__fill {
    height: 100%;
    background: linear-gradient(90deg, #3aab3a, #0bca51);
    transition: width 0.35s ease;
}

.guild-crawl-progress__text {
    margin: 0.45rem 0 0;
    font-size: 0.8rem;
    color: rgba(226, 232, 240, 0.85);
}

.guild-crawl-metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
    margin: 1rem 0 0;
}

.guild-crawl-metrics div {
    border-radius: 10px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: rgba(2, 6, 23, 0.45);
    padding: 0.55rem 0.7rem;
}

.guild-crawl-metrics dt {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: rgba(148, 163, 184, 0.9);
}

.guild-crawl-metrics dd {
    margin: 0.2rem 0 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
}

.guild-crawl-lookups {
    margin-top: 1rem;
}

.guild-crawl-lookups__title {
    margin: 0 0 0.5rem;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgba(148, 163, 184, 0.95);
}

.guild-crawl-lookups__table-wrap {
    max-height: 220px;
    overflow: auto;
    border-radius: 10px;
    border: 1px solid rgba(148, 163, 184, 0.2);
}

.guild-crawl-lookups__table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.78rem;
}

.guild-crawl-lookups__table th {
    position: sticky;
    top: 0;
    background: rgba(15, 23, 42, 0.98);
    text-align: left;
    padding: 0.45rem 0.65rem;
    color: rgba(148, 163, 184, 0.95);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 0.65rem;
}

.guild-crawl-lookups__table td {
    padding: 0.4rem 0.65rem;
    border-top: 1px solid rgba(148, 163, 184, 0.12);
    color: rgba(226, 232, 240, 0.92);
}

.guild-crawl-lookups__row--ok td:nth-child(2) {
    color: #86efac;
}

.guild-crawl-lookups__row--fail td:nth-child(2) {
    color: #fca5a5;
}

.guild-crawl-form {
    margin-top: 1.25rem;
    display: grid;
    gap: 1rem;
}

.guild-crawl-label {
    display: grid;
    gap: 0.35rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: rgba(226, 232, 240, 0.92);
}

.guild-crawl-hint {
    font-weight: 400;
    color: rgba(148, 163, 184, 0.85);
    font-size: 0.75rem;
}

.guild-crawl-textarea {
    width: 100%;
    min-height: 140px;
    resize: vertical;
    border-radius: 10px;
    border: 1px solid rgba(148, 163, 184, 0.25);
    background: rgba(2, 6, 23, 0.65);
    color: #f8fafc;
    padding: 0.65rem 0.75rem;
    font-size: 0.84rem;
    line-height: 1.45;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.guild-crawl-textarea:focus {
    outline: none;
    border-color: rgba(11, 202, 81, 0.55);
    box-shadow: 0 0 0 2px rgba(11, 202, 81, 0.15);
}

.guild-crawl-options {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}

.guild-crawl-options label {
    display: grid;
    gap: 0.3rem;
    font-size: 0.75rem;
    color: rgba(203, 213, 225, 0.95);
}

.guild-crawl-options input[type='number'] {
    border-radius: 8px;
    border: 1px solid rgba(148, 163, 184, 0.25);
    background: rgba(2, 6, 23, 0.65);
    color: #fff;
    padding: 0.4rem 0.55rem;
    font-size: 0.84rem;
}

.guild-crawl-check {
    grid-column: 1 / -1;
    display: flex !important;
    align-items: center;
    gap: 0.5rem;
    flex-direction: row;
}

.guild-crawl-error {
    margin: 0;
    color: #fca5a5;
    font-size: 0.82rem;
}

.guild-crawl-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.guild-crawl-btn {
    border-radius: 10px;
    border: 1px solid rgba(148, 163, 184, 0.28);
    background: rgba(15, 23, 42, 0.8);
    color: #e2e8f0;
    padding: 0.5rem 0.9rem;
    font-size: 0.8rem;
    font-weight: 600;
    transition: background 0.15s ease, border-color 0.15s ease;
}

.guild-crawl-btn:hover:not(:disabled) {
    background: rgba(30, 41, 59, 0.95);
    border-color: rgba(148, 163, 184, 0.45);
}

.guild-crawl-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.guild-crawl-btn--primary {
    border-color: rgba(11, 202, 81, 0.45);
    background: rgba(11, 202, 81, 0.18);
    color: #bbf7d0;
}

.guild-crawl-btn--primary:hover:not(:disabled) {
    background: rgba(11, 202, 81, 0.28);
}

.guild-crawl-btn--ghost {
    background: transparent;
}

.guild-crawl-log {
    margin: 1rem 0 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 0.35rem;
    max-height: 200px;
    overflow: auto;
}

.guild-crawl-log li {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 0.65rem;
    font-size: 0.74rem;
    color: rgba(203, 213, 225, 0.9);
    border-bottom: 1px solid rgba(148, 163, 184, 0.12);
    padding-bottom: 0.3rem;
}

.guild-crawl-log time {
    color: rgba(148, 163, 184, 0.85);
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

@media (max-width: 640px) {
    .guild-crawl-metrics {
        grid-template-columns: 1fr;
    }

    .guild-crawl-options {
        grid-template-columns: 1fr;
    }
}
</style>
