<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { trackFunnelEvent } from '@/lib/funnelAnalytics';

const { t, te } = useI18n();
const page = usePage();

const props = defineProps({
    onboarding: {
        type: Object,
        default: () => null,
    },
});

const steps = computed(() => props.onboarding?.steps || []);
const segment = computed(() => props.onboarding?.segment || 'linked_free');
const copyVariant = computed(() => props.onboarding?.copyVariant || 'a');
const collapsed = ref(false);

const collapseStorageKey = computed(() => {
    const userId = page.props.auth?.user?.id ?? 'guest';
    return `onboarding_collapsed_v1_${userId}`;
});

const routeStepMap = [
    { routeName: 'dashboard', step: 'open_dashboard' },
    { routeName: 'bazaar', step: 'explore_module' },
    { routeName: 'npc-flips', step: 'explore_module' },
    { routeName: 'profile-stats', step: 'explore_module' },
    { routeName: 'event-timer', step: 'explore_module' },
    { routeName: 'mayors', step: 'explore_module' },
    { routeName: 'leaderboards', step: 'explore_module' },
];

function trackOnboardingEvent(action, properties = {}) {
    if (!props.onboarding?.show) {
        return;
    }

    trackFunnelEvent(`onboarding_${action}`, {
        segment: segment.value,
        variant: copyVariant.value,
        completed_count: Number(props.onboarding?.completedCount || 0),
        total_count: Number(props.onboarding?.totalCount || 0),
        ...properties,
    }, {
        path: page.url || undefined,
    });
}

function markStepDone(step, source = 'manual') {
    trackOnboardingEvent('step_complete', {
        step,
        source,
    });

    router.post(route('onboarding.complete-step'), { step }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function dismissChecklist() {
    trackOnboardingEvent('dismiss');

    router.post(route('onboarding.dismiss'), {}, {
        preserveScroll: true,
        preserveState: true,
    });
}

function toggleCollapsed() {
    collapsed.value = !collapsed.value;

    if (typeof window !== 'undefined') {
        window.localStorage.setItem(collapseStorageKey.value, collapsed.value ? '1' : '0');
    }
}

function maybeCompleteCurrentRouteStep() {
    if (!props.onboarding?.show) {
        return;
    }

    for (const item of routeStepMap) {
        if (!route().current(item.routeName)) {
            continue;
        }

        const step = steps.value.find((entry) => entry.key === item.step);
        if (step && !step.completed) {
            markStepDone(item.step);
        }

        break;
    }
}

function resolveStepText(stepKey, field) {
    const segmentKey = `onboarding.steps.${stepKey}.${field}_variants.${segment.value}`;
    if (te(segmentKey)) {
        return t(segmentKey);
    }

    return t(`onboarding.steps.${stepKey}.${field}`);
}

const copyKey = computed(() => {
    const variantKey = `onboarding.copy_variants.${copyVariant.value}`;
    return te(variantKey) ? variantKey : 'onboarding.copy';
});

onMounted(() => {
    if (typeof window !== 'undefined') {
        collapsed.value = window.localStorage.getItem(collapseStorageKey.value) === '1';
    }

    trackOnboardingEvent('view');
    maybeCompleteCurrentRouteStep();
});

watch(
    () => page.url,
    () => {
        maybeCompleteCurrentRouteStep();
    },
);
</script>

<template>
    <div class="onboarding-popup" :class="{ 'onboarding-popup--collapsed': collapsed }">
        <!-- Fixed toggle shown when collapsed -->
        <button
            v-if="collapsed"
            type="button"
            class="collapse-toggle collapse-toggle--fixed"
            @click="toggleCollapsed"
            :aria-pressed="collapsed"
            :aria-label="collapsed ? t('onboarding.title') : t('onboarding.dismiss')"
        >
            <span class="arrow" aria-hidden="true">❯</span>
        </button>

        <transition name="onboarding-slide">
            <section class="onboarding-card" v-if="!collapsed">
                <!-- Inline toggle shown when open, positioned on right inside the card -->
                <button
                    type="button"
                    class="collapse-toggle collapse-toggle--inside"
                    @click="toggleCollapsed"
                    :aria-pressed="collapsed"
                    :aria-label="collapsed ? t('onboarding.title') : t('onboarding.dismiss')"
                >
                    <span class="arrow" aria-hidden="true">❮</span>
                </button>
                <div class="onboarding-header">
                    <div>
                        <p class="onboarding-kicker">{{ t('onboarding.kicker') }}</p>
                        <h2 class="onboarding-title">{{ t('onboarding.title') }}</h2>
                        <p class="onboarding-copy">
                            {{ t(copyKey, {
                                completed: onboarding?.completedCount || 0,
                                total: onboarding?.totalCount || 0,
                            }) }}
                        </p>
                    </div>

                    <button type="button" class="dismiss-link" @click="dismissChecklist">
                        {{ t('onboarding.dismiss') }}
                    </button>
                </div>

                <div class="progress-track" role="progressbar" :aria-valuenow="onboarding?.progressPct || 0" aria-valuemin="0" aria-valuemax="100">
                    <span class="progress-fill" :style="{ width: `${onboarding?.progressPct || 0}%` }"></span>
                </div>

                <div class="step-grid">
                    <article v-for="step in steps" :key="step.key" class="step-card" :class="{ 'step-card--done': step.completed }">
                        <div class="step-meta">
                            <p class="step-title">{{ resolveStepText(step.key, 'title') }}</p>
                            <p class="step-description">{{ resolveStepText(step.key, 'description') }}</p>
                        </div>

                        <div class="step-action">
                            <span v-if="step.completed" class="done-pill">{{ t('onboarding.done') }}</span>
                            <template v-else>
                                <Link v-if="step.routeName" :href="route(step.routeName)" class="go-link">
                                    {{ t('onboarding.goTo') }}
                                </Link>
                                <button type="button" class="done-btn" @click="markStepDone(step.key)">
                                    {{ t('onboarding.markDone') }}
                                </button>
                            </template>
                        </div>
                    </article>
                </div>
            </section>
        </transition>

    </div>
</template>

<style scoped>
.onboarding-popup {
    --onboarding-edge-inset: 1rem;
    --toggle-size: 44px;
    --onboarding-handle-width: var(--toggle-size);
    --slide-duration: 260ms;
    --fade-duration: 180ms;
    --slide-distance: 14px;
    --anim-ease: cubic-bezier(0.16, 1, 0.3, 1);
    position: fixed;
    left: var(--onboarding-edge-inset);
    top: 5.25rem;
    z-index: 60;
    width: min(320px, calc(100vw - 2rem));
    max-height: calc(100vh - 6.5rem);
    overflow: visible;
    transition: transform var(--slide-duration) var(--anim-ease);
    will-change: transform;
}

.onboarding-popup--collapsed {
    width: var(--toggle-size);
    height: var(--toggle-size);
    max-height: none;
    pointer-events: none;
}

.onboarding-slide-enter-active,
.onboarding-slide-leave-active {
    transition: opacity var(--fade-duration) linear, transform var(--slide-duration) var(--anim-ease);
    will-change: transform, opacity;
}

.onboarding-slide-enter-from,
.onboarding-slide-leave-to {
    opacity: 0;
    transform: translate3d(calc(-1 * var(--slide-distance)), 0, 0);
}

.onboarding-slide-enter-to,
.onboarding-slide-leave-from {
    opacity: 1;
    transform: translateX(0);
}

@media (prefers-reduced-motion: reduce) {
    .onboarding-popup {
        transition: none;
    }

    .onboarding-slide-enter-active,
    .onboarding-slide-leave-active {
        transition: none;
    }
}

.onboarding-card {
    position: relative;
    z-index: 1;
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 18px;
    background: linear-gradient(180deg, rgba(16, 16, 16, 0.98), rgba(18, 20, 26, 0.95));
    padding: 0.7rem;
    padding-right: calc(var(--toggle-size) + 0.75rem);
    box-shadow: 0 18px 44px rgba(2, 6, 23, 0.56);
    backdrop-filter: blur(18px);
    overflow-y: auto;
    max-height: calc(100vh - 6.5rem);
}

.onboarding-header {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    align-items: flex-start;
}

.dismiss-link {
    border: 0;
    background: transparent;
    color: rgba(148, 163, 184, 0.9);
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
    padding: 0.15rem 0.25rem;
}

.dismiss-link:hover {
    color: #f8fafc;
}

.onboarding-kicker {
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.18em;
    font-size: 0.6rem;
    font-weight: 700;
    color: rgba(148, 163, 184, 0.86);
}

.onboarding-title {
    margin: 0.35rem 0 0;
    color: #f8fafc;
    font-size: 1.05rem;
    font-weight: 700;
    letter-spacing: -0.02em;
}

.onboarding-copy {
    margin: 0.38rem 0 0;
    color: rgba(226, 232, 240, 0.68);
    font-size: 0.76rem;
    line-height: 1.45;
}

.collapse-toggle {
    --size: var(--toggle-size);
    width: var(--size);
    height: var(--size);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: transparent;
    border: none;
    color: rgba(248,250,252,0.92);
    padding: 0;
}

.collapse-toggle--fixed {
    position: fixed;
    left: 16px;
    top: 40vh;
    transform: translateY(-50%);
    z-index: 120;
}

.collapse-toggle--inside {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 62;
}

/* When checklist is collapsed, show a stronger toggle */
.onboarding-popup--collapsed .collapse-toggle {
    /* make the toggle visually visible when collapsed */
    background: rgba(0,0,0,0.6);
    border-radius: 999px;
    border: 2px solid rgba(255,255,255,0.14);
    box-shadow: 0 10px 36px rgba(2,6,23,0.7);
    color: #fff;
    z-index: 120;
    pointer-events: auto;
}

/* subtle hover for caret only */
.collapse-toggle:hover .arrow { transform: scale(1.05); }
.collapse-toggle .arrow { transition: transform 120ms linear; }

.arrow {
    font-size: 1rem;
    line-height: 1;
    transition: transform 180ms ease;
}


.dismiss-btn {
    display: none;
}

.progress-track {
    margin-top: 0.75rem;
    height: 7px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.07);
    overflow: hidden;
}


.progress-fill {
    display: block;
    height: 100%;
    background: linear-gradient(90deg, rgba(148, 163, 184, 0.55), rgba(229, 231, 235, 0.9));
}

.step-grid {
    margin-top: 0.75rem;
    display: grid;
    gap: 0.55rem;
}

.step-card {
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.025);
    margin-right: 0.35rem;
    padding: 0.7rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    align-items: stretch;
}

.step-card--done {
    border-color: rgba(34, 197, 94, 0.38);
    background: rgba(34, 197, 94, 0.06);
    box-shadow: inset 0 0 0 1px rgba(34, 197, 94, 0.12), 0 0 0 1px rgba(34, 197, 94, 0.04);
}

.step-title {
    margin: 0;
    color: #f8fafc;
    font-size: 0.82rem;
    font-weight: 600;
    line-height: 1.25;
}

.step-description {
    margin: 0.28rem 0 0;
    color: rgba(226, 232, 240, 0.64);
    font-size: 0.72rem;
    line-height: 1.45;
}

.step-action {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
    justify-content: flex-start;
}

.go-link,
.done-btn,
.done-pill {
    border-radius: 999px;
    font-size: 0.78rem;
    padding: 0.45rem 0.72rem;
    font-weight: 600;
}

.go-link {
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: #e5e7eb;
    font-size: 0.66rem;
    padding: 0.3rem 0.55rem;
}

.done-btn {
    border: 1px solid rgba(34, 197, 94, 0.28);
    background: rgba(34, 197, 94, 0.08);
    color: rgba(220, 252, 231, 0.95);
    cursor: pointer;
}

.done-pill {
    border: 1px solid rgba(34, 197, 94, 0.28);
    background: rgba(34, 197, 94, 0.1);
    color: #dcfce7;
}

@media (max-width: 768px) {
    .onboarding-popup {
        --onboarding-edge-inset: 0.75rem;
        top: auto;
        bottom: 0.75rem;
        width: calc(100vw - 1.5rem);
    }
}
</style>
