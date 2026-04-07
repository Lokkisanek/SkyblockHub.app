<script setup>
import { watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

function close() {
    emit('close');
}

function onKeydown(e) {
    if (e.key === 'Escape' && props.show) {
        close();
    }
}

watch(() => props.show, (val) => {
    document.body.style.overflow = val ? 'hidden' : '';
});

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-sm"
                @click="close"
            />
        </Transition>

        <!-- Panel -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <div
                v-if="show"
                class="auth-panel fixed inset-y-0 right-0 z-[101] flex w-full max-w-md flex-col overflow-y-auto"
            >
                <!-- Close button -->
                <button
                    @click="close"
                    class="absolute right-4 top-4 z-10 rounded-md p-1.5 text-neutral transition hover:bg-white/[0.06] hover:text-white"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6L6 18M6 6l12 12" />
                    </svg>
                </button>

                <div class="flex flex-1 flex-col justify-center px-8 py-12">
                    <h2 class="mb-2 text-2xl font-bold tracking-wide text-white">Welcome to SkyblockHub</h2>
                    <p class="mb-8 text-[13px] text-neutral">
                        Sign in or create an account using one of the options below.
                    </p>

                    <!-- OAuth buttons -->
                    <div class="space-y-3">
                        <a :href="route('auth.discord')" class="social-btn-large">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20.317 4.37a19.791 19.791 0 00-4.885-1.515.074.074 0 00-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 00-5.487 0 12.64 12.64 0 00-.617-1.25.077.077 0 00-.079-.037A19.736 19.736 0 003.677 4.37a.07.07 0 00-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 00.031.057 19.9 19.9 0 005.993 3.03.078.078 0 00.084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 00-.041-.106 13.107 13.107 0 01-1.872-.892.077.077 0 01-.008-.128 10.2 10.2 0 00.372-.292.074.074 0 01.077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 01.078.01c.12.098.246.198.373.292a.077.077 0 01-.006.127 12.299 12.299 0 01-1.873.892.077.077 0 00-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 00.084.028 19.839 19.839 0 006.002-3.03.077.077 0 00.032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 00-.031-.03z"/>
                            </svg>
                            Continue with Discord
                        </a>
                    </div>

                    <p class="mt-8 text-center text-[11px] leading-relaxed text-neutral/60">
                        By signing in, you agree to our
                        <a href="/terms" class="underline transition hover:text-neutral">Terms</a> and
                        <a href="/privacy" class="underline transition hover:text-neutral">Privacy Policy</a>.
                    </p>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.auth-panel {
    background: linear-gradient(180deg, rgba(16, 16, 16, 0.98) 0%, rgba(16, 16, 16, 0.95) 100%);
    backdrop-filter: blur(16px);
    border-left: 1px solid rgba(255, 255, 255, 0.06);
}

.social-btn-large {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 12px 0;
    font-size: 14px;
    font-weight: 500;
    color: #aaa;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s, color 0.2s;
    text-decoration: none;
}

.social-btn-large:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.1);
}
</style>
