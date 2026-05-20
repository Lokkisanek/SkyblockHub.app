<script setup>
import AdminOperationsPanel from '@/Components/Admin/AdminOperationsPanel.vue';
import GuildCrawlPanel from '@/Components/Admin/GuildCrawlPanel.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    operations: { type: Object, default: () => ({}) },
    guildCrawl: { type: Object, default: () => ({}) },
});
</script>

<template>
    <Head title="Admin" />

    <AuthenticatedLayout>
        <div class="min-h-screen px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <section class="hero-card mb-6">
                    <div>
                        <p class="hero-kicker">Admin</p>
                        <h1 class="hero-title">Operations</h1>
                        <p class="hero-copy">
                            Hypixel API health, profile cache, leaderboard snapshot, and guild profile crawl.
                        </p>
                        <p class="admin-quick-links">
                            <Link class="admin-quick-links__a" :href="route('admin.guides.submissions')">Guide submissions</Link>
                            <span class="admin-quick-links__sep" aria-hidden="true">·</span>
                            <Link class="admin-quick-links__a" :href="route('admin.trust-index.submissions')">Trust Index queue</Link>
                        </p>
                    </div>
                </section>

                <AdminOperationsPanel :operations="operations" />

                <GuildCrawlPanel :initial="guildCrawl" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.hero-card {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.88), rgba(30, 41, 59, 0.9));
    border-radius: 18px;
    padding: 1.25rem;
}

.hero-kicker {
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.18em;
    font-size: 0.68rem;
    color: rgba(148, 163, 184, 0.9);
    font-weight: 700;
}

.hero-title {
    margin: 0.35rem 0 0;
    color: #f8fafc;
    font-size: clamp(1.35rem, 2.1vw, 2rem);
    line-height: 1.1;
    font-weight: 800;
}

.hero-copy {
    margin: 0.45rem 0 0;
    color: rgba(226, 232, 240, 0.82);
    max-width: 70ch;
    font-size: 0.92rem;
}

.admin-quick-links {
    margin: 0.85rem 0 0;
    font-size: 0.875rem;
    color: rgba(226, 232, 240, 0.75);
}

.admin-quick-links__a {
    color: rgba(34, 211, 238, 0.95);
    text-decoration: none;
    font-weight: 600;
}

.admin-quick-links__a:hover {
    color: #fff;
    text-decoration: underline;
}

.admin-quick-links__sep {
    margin: 0 0.35rem;
    opacity: 0.45;
}
</style>
