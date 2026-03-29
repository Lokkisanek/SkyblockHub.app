<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Privacy Policy" />

    <AuthenticatedLayout>
        <div class="pt-14 pb-20 sm:pt-16 sm:pb-24">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">

                <h1 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">Privacy Policy</h1>
                <p class="mt-2 text-sm text-white/40">Last updated: March 29, 2026</p>

                <div class="legal-content mt-10 space-y-8">

                    <section>
                        <h2>1. Information We Collect</h2>
                        <p>SkyblockHub collects minimal data required to provide its services:</p>
                        <ul>
                            <li><strong>Account data:</strong> When you register, we store your email address and authentication credentials (Discord or direct login).</li>
                            <li><strong>Minecraft username:</strong> Provided voluntarily for profile lookups via the Hypixel API.</li>
                            <li><strong>Usage data:</strong> Basic server logs (IP addresses, timestamps) for security and abuse prevention. No session-ID tracking or analytics fingerprinting is used.</li>
                        </ul>
                    </section>

                    <section>
                        <h2>2. How We Use Your Data</h2>
                        <ul>
                            <li>To authenticate your account and provide personalized features.</li>
                            <li>To fetch Hypixel Skyblock data on your behalf via public APIs.</li>
                            <li>To maintain security and prevent abuse of the platform.</li>
                        </ul>
                    </section>

                    <section>
                        <h2>3. Data Sharing</h2>
                        <p>We do not sell, rent, or share your personal data with third parties. Data is only transmitted to:</p>
                        <ul>
                            <li>Hypixel API — to retrieve your Skyblock profile (using your Minecraft username).</li>
                            <li>Authentication providers (Discord OAuth) — only during login flow.</li>
                        </ul>
                    </section>

                    <section>
                        <h2>4. Cookies</h2>
                        <p>SkyblockHub uses essential cookies only — session cookies required for authentication. No advertising or tracking cookies are used.</p>
                    </section>

                    <section>
                        <h2>5. Data Retention</h2>
                        <p>Account data is retained for as long as your account is active. You can request deletion of your account and all associated data by contacting us.</p>
                    </section>

                    <section>
                        <h2>6. Your Rights</h2>
                        <p>Under applicable data protection laws (including GDPR), you have the right to:</p>
                        <ul>
                            <li>Access your personal data.</li>
                            <li>Request correction or deletion of your data.</li>
                            <li>Withdraw consent at any time.</li>
                            <li>Lodge a complaint with a supervisory authority.</li>
                        </ul>
                    </section>

                    <section>
                        <h2>7. Contact</h2>
                        <p>For privacy-related inquiries, contact us at <a href="mailto:odehnalm.08@spst.eu" class="text-emerald-400/80 hover:text-emerald-300">odehnalm.08@spst.eu</a>.</p>
                    </section>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.legal-content h2 {
    font-size: 1rem;
    font-weight: 600;
    color: white;
    margin-bottom: 0.75rem;
}

.legal-content p {
    font-size: 0.875rem;
    line-height: 1.7;
    color: rgba(255, 255, 255, 0.5);
}

.legal-content ul {
    margin-top: 0.5rem;
    padding-left: 1.25rem;
    list-style-type: disc;
}

.legal-content li {
    font-size: 0.875rem;
    line-height: 1.7;
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 0.25rem;
}

.legal-content strong {
    color: rgba(255, 255, 255, 0.7);
}
</style>
