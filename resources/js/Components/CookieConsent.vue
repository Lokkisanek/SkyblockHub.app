<script setup>
import { ref, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const visible = ref(false);

onMounted(() => {
    if (!localStorage.getItem('cookie_consent')) {
        visible.value = true;
    }
});

function saveConsent(level) {
    localStorage.setItem('cookie_consent', level);
    visible.value = false;

    router.post(route('cookie-consent.store'), { level }, {
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-4 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-4 opacity-0"
    >
        <div
            v-if="visible"
            class="fixed bottom-5 right-5 z-50 w-full max-w-sm rounded-2xl border border-white/10 bg-surface-900/95 p-5 shadow-[0_16px_48px_rgba(0,0,0,0.5)] backdrop-blur-xl"
        >
            <div class="mb-1 flex items-center gap-2">
                <span class="text-lg">🍪</span>
                <h3 class="text-sm font-bold text-white">{{ $t('cookies.title') }}</h3>
            </div>
            <p class="text-xs leading-relaxed text-white/55">
                <i18n-t keypath="cookies.description" tag="span">
                    <template #essentialBold>
                        <strong class="text-white/70">{{ $t('cookies.essential') }}</strong>
                    </template>
                    <template #privacyLink>
                        <Link :href="route('privacy')" class="text-white/70 underline underline-offset-2 hover:text-white">{{ $t('cookies.privacyPolicy') }}</Link>
                    </template>
                </i18n-t>
            </p>
            <div class="mt-4 flex items-center gap-2">
                <button
                    @click="saveConsent('all')"
                    class="flex-1 rounded-lg border border-profit/40 bg-profit/15 py-2 text-xs font-semibold text-profit transition hover:bg-profit/25 hover:text-white"
                >
                    {{ $t('cookies.allowAll') }}
                </button>
                <button
                    @click="saveConsent('essential')"
                    class="flex-1 rounded-lg border border-white/10 bg-white/5 py-2 text-xs font-semibold text-white/50 transition hover:bg-white/10 hover:text-white/80"
                >
                    {{ $t('cookies.onlyEssential') }}
                </button>
            </div>
        </div>
    </Transition>
</template>
