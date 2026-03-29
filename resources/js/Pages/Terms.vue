<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Terms of Service" />

    <AuthenticatedLayout>
        <div class="pt-14 pb-20 sm:pt-16 sm:pb-24">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">

                <h1 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">Terms of Service</h1>
                <p class="mt-2 text-sm text-white/40">Last updated: March 29, 2026</p>

                <div class="legal-content mt-10 space-y-8">

                    <section>
                        <h2>1. Acceptance of Terms</h2>
                        <p>By accessing or using SkyblockHub, you agree to be bound by these Terms of Service. If you do not agree, do not use the platform.</p>
                    </section>

                    <section>
                        <h2>2. Description of Service</h2>
                        <p>SkyblockHub is a free, open-source web application providing tools for Hypixel Skyblock players, including Bazaar tracking, NPC arbitrage, event timers, mayor intelligence, and profile analytics. The service is provided "as is" without warranties of any kind.</p>
                    </section>

                    <section>
                        <h2>3. User Accounts</h2>
                        <ul>
                            <li>You must provide accurate information when creating an account.</li>
                            <li>You are responsible for maintaining the security of your account credentials.</li>
                            <li>You must not share your account with others or use another user's account.</li>
                        </ul>
                    </section>

                    <section>
                        <h2>4. Acceptable Use</h2>
                        <p>You agree not to:</p>
                        <ul>
                            <li>Use the platform for any illegal purpose.</li>
                            <li>Attempt to gain unauthorized access to the platform or its systems.</li>
                            <li>Abuse the API or scrape data at excessive rates.</li>
                            <li>Interfere with other users' access to the service.</li>
                            <li>Use automated tools to interact with the platform without permission.</li>
                        </ul>
                    </section>

                    <section>
                        <h2>5. Intellectual Property</h2>
                        <p>SkyblockHub is open-source software. The source code is available under its respective license on GitHub. "SkyblockHub" branding and logo are property of the project maintainer.</p>
                    </section>

                    <section>
                        <h2>6. Third-Party Services</h2>
                        <p>SkyblockHub relies on the Hypixel API and other third-party services. We are not responsible for the availability, accuracy, or terms of these external services.</p>
                    </section>

                    <section>
                        <h2>7. Disclaimer</h2>
                        <p>SkyblockHub is not affiliated with, endorsed by, or associated with Hypixel Inc., Mojang Studios, or Microsoft Corporation. All game-related content and trademarks belong to their respective owners.</p>
                    </section>

                    <section>
                        <h2>8. Limitation of Liability</h2>
                        <p>SkyblockHub and its maintainer shall not be liable for any direct, indirect, incidental, or consequential damages arising from the use or inability to use the platform, including but not limited to loss of in-game currency or items based on data provided by the platform.</p>
                    </section>

                    <section>
                        <h2>9. Changes to Terms</h2>
                        <p>We reserve the right to modify these terms at any time. Continued use of the platform after changes constitutes acceptance of the updated terms.</p>
                    </section>

                    <section>
                        <h2>10. Contact</h2>
                        <p>Questions about these terms? Contact us at <a href="mailto:odehnalm.08@spst.eu" class="text-emerald-400/80 hover:text-emerald-300">odehnalm.08@spst.eu</a>.</p>
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
