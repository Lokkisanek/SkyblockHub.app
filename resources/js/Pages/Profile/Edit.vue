<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import LinkMinecraftModal from '@/Components/LinkMinecraftModal.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    isMcLinked: {
        type: Boolean,
        default: false,
    },
    minecraftUsername: {
        type: String,
        default: null,
    },
    discordUsername: {
        type: String,
        default: null,
    },
    subscriptionFeatures: {
        type: Object,
        default: () => ({}),
    },
    paymentStatus: {
        type: Object,
        default: () => ({}),
    },
});

const showLinkModal = ref(false);

const mcForm = useForm({
    minecraft_username: props.discordUsername ?? '',
});

const unlinkForm = useForm({});
const cancelSubscriptionForm = useForm({});

function linkDirect() {
    mcForm.post(route('mc.link.direct'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="mx-auto max-w-3xl space-y-5 px-4 sm:px-6 lg:px-8">
                <!-- Flash status message -->
                <div
                    v-if="status"
                    class="rounded-lg border border-[#0bca51]/30 bg-[#0bca51]/10 px-4 py-3 text-sm text-[#55FF55]"
                >
                    {{ status }}
                </div>

                <!-- Profile Information -->
                <div class="rounded-lg border border-border bg-surface-800 p-5 sm:p-6">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <!-- Minecraft Link Section -->
                <div class="rounded-lg border border-border bg-surface-800 p-5 sm:p-6">
                    <section class="max-w-xl">
                        <header>
                            <h2 class="text-base font-semibold text-white">
                                {{ $t('profile.minecraft.heading') }}
                            </h2>
                            <p class="mt-1 text-sm text-neutral">
                                <template v-if="isMcLinked">
                                    {{ $t('profile.minecraft.linked') }}
                                </template>
                                <template v-else>
                                    {{ $t('profile.minecraft.notLinked') }}
                                </template>
                            </p>
                        </header>

                        <div class="mt-4">
                            <template v-if="isMcLinked">
                                <div class="flex items-center gap-3 rounded-lg border border-[#0bca51]/25 bg-[#0bca51]/8 p-3">
                                    <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded">
                                        <img
                                            :src="`https://mc-heads.net/avatar/${minecraftUsername}/40`"
                                            :alt="minecraftUsername"
                                            class="h-10 w-10"
                                        />
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-semibold text-white">{{ minecraftUsername }}</p>
                                        <p class="text-xs text-[#55FF55]">{{ $t('profile.minecraft.connectedAs') }}</p>
                                    </div>
                                    <button
                                        @click="unlinkForm.post(route('mc.unlink'), { preserveScroll: true })"
                                        :disabled="unlinkForm.processing"
                                        class="rounded-md border border-border bg-surface-700 px-3 py-1.5 text-xs font-medium text-neutral hover:bg-surface-600 hover:text-white disabled:opacity-50 transition-colors"
                                    >
                                        {{ $t('profile.minecraft.changeAccount') }}
                                    </button>
                                </div>
                            </template>
                            <template v-else>
                                <div class="flex flex-col gap-4">
                                    <div v-if="!discordUsername">
                                        <p class="text-sm text-neutral mb-3">
                                            {{ $t('profile.minecraft.discordRequired') }}
                                        </p>
                                        <a
                                            :href="route('auth.discord.link')"
                                            class="inline-flex items-center gap-2 rounded-md bg-[#5865F2] px-4 py-2 text-sm font-medium text-white hover:bg-[#4752C4] transition-colors"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z"/>
                                            </svg>
                                            {{ $t('profile.minecraft.linkDiscord') }}
                                        </a>
                                    </div>

                                    <div v-else>
                                        <p class="text-sm text-neutral mb-2">
                                            {{ $t('profile.minecraft.discordLinkedAs') }} <strong class="text-white">{{ discordUsername }}</strong>.
                                            {{ $t('profile.minecraft.enterMcName') }}
                                        </p>

                                        <button
                                            @click="showLinkModal = true"
                                            class="mb-3 text-sm text-[#0bca51] hover:text-[#55FF55] underline transition-colors"
                                        >
                                            {{ $t('profile.minecraft.howToSetup') }}
                                        </button>

                                        <div class="flex gap-2">
                                            <input
                                                id="mc-username-direct"
                                                v-model="mcForm.minecraft_username"
                                                type="text"
                                                :placeholder="$t('profile.minecraft.mcPlaceholder')"
                                                class="block w-full rounded-md border-border bg-surface-700 text-white shadow-sm placeholder-neutral/50 focus:border-[#0bca51] focus:ring-[#0bca51] sm:text-sm"
                                                @keyup.enter="linkDirect"
                                            />
                                            <button
                                                @click="linkDirect"
                                                :disabled="mcForm.processing || !mcForm.minecraft_username.trim()"
                                                class="inline-flex items-center rounded-md bg-[#0bca51] px-4 py-2 text-sm font-medium text-surface-900 hover:bg-[#0bca51]/85 disabled:opacity-50 whitespace-nowrap transition-colors"
                                            >
                                                <span v-if="mcForm.processing">{{ $t('profile.minecraft.verifying') }}</span>
                                                <span v-else>{{ $t('profile.minecraft.link') }}</span>
                                            </button>
                                        </div>
                                        <p v-if="mcForm.errors.minecraft_username" class="mt-1.5 text-xs text-[#FF5555]">
                                            {{ mcForm.errors.minecraft_username }}
                                        </p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </section>
                </div>

                <!-- Danger zone -->
                <div class="rounded-lg border border-border bg-surface-800 p-5 sm:p-6">
                    <section class="max-w-xl">
                        <header>
                            <h2 class="text-base font-semibold text-white">{{ $t('profile.payments.heading') }}</h2>
                            <p class="mt-1 text-sm text-neutral">{{ $t('profile.payments.description') }}</p>
                        </header>

                        <div class="mt-4 space-y-2 text-sm text-neutral">
                            <p>
                                {{ $t('profile.payments.tier') }}:
                                <strong class="text-white">{{ String(paymentStatus.tier || 'free').toUpperCase() }}</strong>
                            </p>
                            <p>
                                {{ $t('profile.payments.status') }}:
                                <strong class="text-white">{{ paymentStatus.status || 'inactive' }}</strong>
                            </p>
                            <p v-if="paymentStatus.trialEndsAt">
                                {{ $t('profile.payments.trialEndsAt') }}:
                                <strong class="text-white">{{ paymentStatus.trialEndsAt }}</strong>
                            </p>
                            <p v-if="paymentStatus.currentPeriodEndsAt">
                                {{ $t('profile.payments.currentPeriodEndsAt') }}:
                                <strong class="text-white">{{ paymentStatus.currentPeriodEndsAt }}</strong>
                            </p>
                        </div>

                        <div class="mt-4 flex items-center gap-2">
                            <button
                                v-if="paymentStatus.hasSubscription"
                                @click="cancelSubscriptionForm.post(route('billing.cancel'), { preserveScroll: true })"
                                :disabled="cancelSubscriptionForm.processing"
                                class="rounded-md border border-red-400/30 bg-red-500/10 px-3 py-1.5 text-xs font-medium text-red-200 hover:bg-red-500/20 disabled:opacity-50 transition-colors"
                            >
                                <span v-if="cancelSubscriptionForm.processing">{{ $t('profile.payments.cancelling') }}</span>
                                <span v-else>{{ $t('profile.payments.cancelSubscription') }}</span>
                            </button>

                            <a
                                :href="route('billing')"
                                class="rounded-md border border-border bg-surface-700 px-3 py-1.5 text-xs font-medium text-neutral hover:bg-surface-600 hover:text-white transition-colors"
                            >
                                {{ $t('profile.payments.openBilling') }}
                            </a>
                        </div>
                    </section>
                </div>

                <div class="rounded-lg border border-border bg-surface-800 p-5 sm:p-6">
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>

        <LinkMinecraftModal
            :show="showLinkModal"
            @close="showLinkModal = false"
        />
    </AuthenticatedLayout>
</template>
