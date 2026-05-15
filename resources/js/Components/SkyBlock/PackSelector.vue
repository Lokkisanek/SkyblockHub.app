<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { useI18n } from '@/strings/useI18n';
import {
    PACK_CONFIGS,
    DEFAULT_PACKS,
    loadEnabledPacks,
    loadPerformanceMode,
    saveEnabledPacks,
    savePerformanceMode,
} from '@/utils/profileViewerSettings';

const emit = defineEmits(['update:packs', 'update:performance']);

const { t } = useI18n();

const showModal = ref(false);
const enabledPacks = ref([...DEFAULT_PACKS]);
const performanceMode = ref(false);

function closeModal() {
    showModal.value = false;
}

function emitPacks() {
    emit('update:packs', [...enabledPacks.value]);
}

function emitPerformance() {
    emit('update:performance', performanceMode.value);
    emitPacks();
}

function setPackEnabled(packId, enabled) {
    const idx = enabledPacks.value.indexOf(packId);
    if (enabled && idx < 0) {
        enabledPacks.value.push(packId);
    } else if (!enabled && idx >= 0) {
        enabledPacks.value.splice(idx, 1);
    }
    saveEnabledPacks(enabledPacks.value);
    emitPacks();
}

function togglePack(packId) {
    setPackEnabled(packId, !isEnabled(packId));
}

function togglePerformance() {
    performanceMode.value = !performanceMode.value;
    savePerformanceMode(performanceMode.value);
    emitPerformance();
}

function isEnabled(packId) {
    return enabledPacks.value.includes(packId);
}

function onKeydown(e) {
    if (e.key === 'Escape' && showModal.value) {
        closeModal();
    }
}

watch(showModal, (open) => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = open ? 'hidden' : '';
});

onMounted(() => {
    enabledPacks.value = loadEnabledPacks();
    performanceMode.value = loadPerformanceMode();
    emitPerformance();
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <button
        type="button"
        class="inline-flex items-center gap-2 rounded-xl border border-border/80 bg-surface-800/80 px-3 py-2 text-sm font-medium text-neutral shadow-sm transition hover:border-border-light hover:text-white focus:outline-none focus:ring-2 focus:ring-profit/25"
        :aria-expanded="showModal"
        aria-haspopup="dialog"
        @click="showModal = true"
    >
        <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-4 w-4 shrink-0 opacity-80"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
        >
            <path
                fill-rule="evenodd"
                d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z"
                clip-rule="evenodd"
            />
        </svg>
        <span class="hidden sm:inline">{{ t('profileStats.viewerSettings') }}</span>
    </button>

    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showModal"
                class="fixed inset-0 z-[9998] flex items-center justify-center p-4 sm:p-6"
                role="presentation"
            >
                <div
                    class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                    @click="closeModal"
                />

                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 scale-[0.98] translate-y-1"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-[0.98] translate-y-1"
                    appear
                >
                    <div
                        v-if="showModal"
                        class="relative z-10 flex max-h-[min(90vh,640px)] w-full max-w-md flex-col overflow-hidden rounded-2xl border border-border/80 bg-surface-900/95 shadow-[0_16px_48px_rgba(0,0,0,0.5)] backdrop-blur-md"
                        role="dialog"
                        aria-modal="true"
                        :aria-label="t('profileStats.viewerSettings')"
                        @click.stop
                    >
                        <header class="shrink-0 border-b border-white/[0.06] px-5 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-neutral">
                                        {{ t('profileStats.viewerSettingsKicker') }}
                                    </p>
                                    <h2 class="mt-1 text-lg font-semibold tracking-tight text-white">
                                        {{ t('profileStats.viewerSettings') }}
                                    </h2>
                                </div>
                                <button
                                    type="button"
                                    class="shrink-0 rounded-lg p-1.5 text-neutral transition hover:bg-white/[0.06] hover:text-white"
                                    :aria-label="t('profileStats.viewerSettingsClose')"
                                    @click="closeModal"
                                >
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                        <path d="M18 6L6 18M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </header>

                        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                            <section>
                                <h3 class="text-[10px] font-bold uppercase tracking-widest text-neutral">
                                    {{ t('profileStats.viewerPacksTitle') }}
                                </h3>
                                <p class="mt-1.5 text-[13px] leading-relaxed text-white/50">
                                    {{ t('profileStats.viewerPacksDesc') }}
                                </p>

                                <ul class="mt-3 space-y-2">
                                    <li
                                        v-for="pack in PACK_CONFIGS"
                                        :key="pack.id"
                                    >
                                        <button
                                            type="button"
                                            class="viewer-option group w-full text-left"
                                            :class="{ 'viewer-option--on': isEnabled(pack.id) }"
                                            :aria-pressed="isEnabled(pack.id)"
                                            @click="togglePack(pack.id)"
                                        >
                                            <img
                                                :src="`/resourcepacks/${pack.folder}/pack.png`"
                                                alt=""
                                                class="viewer-pack-icon h-9 w-9 shrink-0 rounded-md border border-white/[0.06] bg-black/30 object-contain p-0.5"
                                                width="36"
                                                height="36"
                                                loading="lazy"
                                                decoding="async"
                                            />
                                            <span class="min-w-0 flex-1">
                                                <span class="block text-sm font-medium text-white">{{ pack.name }}</span>
                                                <span class="block text-[11px] text-white/40">{{ pack.version }} · {{ pack.author }}</span>
                                            </span>
                                            <span
                                                class="viewer-switch"
                                                :class="{ 'viewer-switch--on': isEnabled(pack.id) }"
                                                aria-hidden="true"
                                            >
                                                <span class="viewer-switch__knob" />
                                            </span>
                                        </button>
                                    </li>
                                </ul>
                            </section>

                            <section class="mt-6 border-t border-white/[0.06] pt-5">
                                <h3 class="text-[10px] font-bold uppercase tracking-widest text-neutral">
                                    {{ t('profileStats.viewerPerfTitle') }}
                                </h3>
                                <p class="mt-1.5 text-[13px] leading-relaxed text-white/50">
                                    {{ t('profileStats.viewerPerfDesc') }}
                                </p>

                                <button
                                    type="button"
                                    class="viewer-option group mt-3 w-full text-left"
                                    :class="{ 'viewer-option--on': performanceMode }"
                                    :aria-pressed="performanceMode"
                                    @click="togglePerformance"
                                >
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-white/[0.06] bg-white/[0.03] text-neutral group-hover:text-white">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M11.3 1.046a1 1 0 011.414 0l1.59 1.59a1 1 0 01.293.707V5.5a1 1 0 01-1 1h-1.172a2 2 0 00-1.414.586l-.828.828A2 2 0 008.172 9H6a1 1 0 01-1-1V6.793a1 1 0 01.293-.707l1.59-1.59zM6 11a1 1 0 011-1h2.172a2 2 0 011.414.586l.828.828A2 2 0 0011.828 13H13a1 1 0 011 1v1.707a1 1 0 01-.293.707l-1.59 1.59a1 1 0 01-1.414 0l-1.59-1.59A1 1 0 019.5 16.5V15a1 1 0 00-1-1H6.328a2 2 0 01-1.414-.586l-.828-.828A2 2 0 003.172 12H2a1 1 0 01-1-1v-2.172a2 2 0 01.586-1.414l.828-.828A2 2 0 004.172 7H6a1 1 0 011 1v1.172a2 2 0 00.586 1.414l.828.828zM16 8a1 1 0 10-2 0 1 1 0 002 0zm-1 4a1 1 0 11-2 0 1 1 0 012 0zm-5 3a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-medium text-white">{{ t('profileStats.viewerPerfLabel') }}</span>
                                        <span class="block text-[11px] leading-snug text-white/40">{{ t('profileStats.viewerPerfHint') }}</span>
                                    </span>
                                    <span
                                        class="viewer-switch"
                                        :class="{ 'viewer-switch--on': performanceMode }"
                                        aria-hidden="true"
                                    >
                                        <span class="viewer-switch__knob" />
                                    </span>
                                </button>
                            </section>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.viewer-option {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 0.75rem;
    background: rgba(255, 255, 255, 0.02);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
    transition:
        border-color 0.15s ease,
        background 0.15s ease;
}

.viewer-option:hover:not(:disabled) {
    border-color: rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.04);
}

.viewer-option--on {
    border-color: rgba(85, 255, 85, 0.22);
    background: rgba(85, 255, 85, 0.05);
}

.viewer-option--on:hover:not(:disabled) {
    border-color: rgba(85, 255, 85, 0.32);
    background: rgba(85, 255, 85, 0.08);
}

.viewer-option--disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.viewer-option:disabled {
    cursor: not-allowed;
}

.viewer-pack-icon {
    image-rendering: pixelated;
    image-rendering: -moz-crisp-edges;
}

.viewer-switch {
    position: relative;
    flex-shrink: 0;
    width: 2.25rem;
    height: 1.25rem;
    border-radius: 9999px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.35);
    transition:
        border-color 0.15s ease,
        background 0.15s ease;
}

.viewer-switch--on {
    border-color: rgba(85, 255, 85, 0.4);
    background: rgba(85, 255, 85, 0.18);
}

.viewer-switch__knob {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 0.875rem;
    height: 0.875rem;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.85);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.35);
    transition: transform 0.15s ease;
}

.viewer-switch--on .viewer-switch__knob {
    transform: translateX(1rem);
    background: #55ff55;
}
</style>
