<script setup>
import AdminOperationsPanel from '@/Components/Admin/AdminOperationsPanel.vue';
import GuildCrawlPanel from '@/Components/Admin/GuildCrawlPanel.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

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
</style>
