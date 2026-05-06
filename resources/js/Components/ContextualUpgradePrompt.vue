<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { trackFunnelEvent } from '@/lib/funnelAnalytics';

const props = defineProps({
    moduleKey: {
        type: String,
        default: 'upgrade_prompt',
    },
    experimentKey: {
        type: String,
        default: '',
    },
    targetTier: {
        type: String,
        default: 'vip',
    },
    kicker: {
        type: String,
        default: '',
    },
    freeTitle: {
        type: String,
        required: true,
    },
    freeTitleB: {
        type: String,
        default: '',
    },
    freeBody: {
        type: String,
        required: true,
    },
    freeBodyB: {
        type: String,
        default: '',
    },
    premiumTitle: {
        type: String,
        default: '',
    },
    premiumTitleB: {
        type: String,
        default: '',
    },
    premiumBody: {
        type: String,
        default: '',
    },
    premiumBodyB: {
        type: String,
        default: '',
    },
    ctaLabel: {
        type: String,
        required: true,
    },
    compareLabel: {
        type: String,
        required: true,
    },
    ctaHref: {
        type: String,
        default: '',
    },
    compareHref: {
        type: String,
        default: '',
    },
});

const page = usePage();
const tierRank = {
    free: 0,
    vip: 1,
    mvp: 2,
};

const activeTier = computed(() => String(page.props.subscriptionFeatures?.tier || 'free').toLowerCase());
const activeRank = computed(() => tierRank[activeTier.value] ?? 0);
const targetRank = computed(() => tierRank[String(props.targetTier || 'vip').toLowerCase()] ?? 1);
const visible = computed(() => activeRank.value < targetRank.value);
const variant = ref('a');
const impressionSent = ref(false);

const title = computed(() => {
    if (!visible.value) {
        return '';
    }

    const isVariantB = variant.value === 'b';

    if (activeRank.value > 0 && props.premiumTitle) {
        if (isVariantB && props.premiumTitleB) {
            return props.premiumTitleB;
        }

        return props.premiumTitle;
    }

    if (isVariantB && props.freeTitleB) {
        return props.freeTitleB;
    }

    return props.freeTitle;
});

const body = computed(() => {
    if (!visible.value) {
        return '';
    }

    const isVariantB = variant.value === 'b';

    if (activeRank.value > 0 && props.premiumBody) {
        if (isVariantB && props.premiumBodyB) {
            return props.premiumBodyB;
        }

        return props.premiumBody;
    }

    if (isVariantB && props.freeBodyB) {
        return props.freeBodyB;
    }

    return props.freeBody;
});

const ctaHref = computed(() => props.ctaHref || route('billing'));
const compareHref = computed(() => props.compareHref || `${route('billing')}#faq`);

function hashToVariant(value) {
    let hash = 0;
    for (let i = 0; i < value.length; i += 1) {
        hash = (hash << 5) - hash + value.charCodeAt(i);
        hash |= 0;
    }

    return Math.abs(hash) % 2 === 0 ? 'a' : 'b';
}

function resolveVariant() {
    if (!props.experimentKey) {
        return 'a';
    }

    const userId = page.props.auth?.user?.id;
    if (userId) {
        return hashToVariant(`${props.experimentKey}:${userId}`);
    }

    if (typeof window === 'undefined') {
        return 'a';
    }

    const storageKey = `upgrade_prompt_variant_v1_${props.experimentKey}`;
    const stored = window.localStorage.getItem(storageKey);
    if (stored === 'a' || stored === 'b') {
        return stored;
    }

    const next = Math.random() > 0.5 ? 'b' : 'a';
    window.localStorage.setItem(storageKey, next);
    return next;
}

function trackPrompt(action) {
    trackFunnelEvent(`upgrade_prompt_${action}`, {
        module: props.moduleKey,
        experiment: props.experimentKey || 'baseline',
        variant: variant.value,
        target_tier: String(props.targetTier || 'vip').toLowerCase(),
        active_tier: activeTier.value,
    }, {
        path: page.url || undefined,
    });
}

onMounted(() => {
    variant.value = resolveVariant();
});

watch([visible, variant], () => {
    if (!visible.value || impressionSent.value) {
        return;
    }

    impressionSent.value = true;
    trackPrompt('impression');
});

function onPrimaryClick() {
    trackPrompt('cta');
}

function onSecondaryClick() {
    trackPrompt('compare');
}
</script>

<template>
    <div v-if="visible" class="upgrade-prompt">
        <div class="upgrade-prompt__copy">
            <p v-if="kicker" class="upgrade-prompt__kicker">{{ kicker }}</p>
            <h3 class="upgrade-prompt__title">{{ title }}</h3>
            <p class="upgrade-prompt__body">{{ body }}</p>
        </div>

        <div class="upgrade-prompt__actions">
            <Link :href="ctaHref" class="upgrade-prompt__primary" @click="onPrimaryClick">
                {{ ctaLabel }}
            </Link>
            <Link :href="compareHref" class="upgrade-prompt__secondary" @click="onSecondaryClick">
                {{ compareLabel }}
            </Link>
        </div>
    </div>
</template>

<style scoped>
.upgrade-prompt {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-radius: 18px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: linear-gradient(180deg, rgba(14, 14, 16, 0.96), rgba(20, 20, 24, 0.92));
    box-shadow: 0 14px 34px rgba(2, 6, 23, 0.38);
    padding: 1rem 1.1rem;
    backdrop-filter: blur(14px);
}

.upgrade-prompt__copy {
    min-width: min(100%, 18rem);
    flex: 1 1 22rem;
}

.upgrade-prompt__kicker {
    margin: 0;
    color: rgba(148, 163, 184, 0.86);
    text-transform: uppercase;
    letter-spacing: 0.18em;
    font-size: 0.62rem;
    font-weight: 700;
}

.upgrade-prompt__title {
    margin: 0.35rem 0 0;
    color: #f8fafc;
    font-size: 0.98rem;
    font-weight: 700;
    line-height: 1.35;
}

.upgrade-prompt__body {
    margin: 0.35rem 0 0;
    color: rgba(226, 232, 240, 0.68);
    font-size: 0.82rem;
    line-height: 1.5;
    max-width: 65ch;
}

.upgrade-prompt__actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: center;
}

.upgrade-prompt__primary,
.upgrade-prompt__secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    padding: 0.48rem 0.82rem;
    font-size: 0.72rem;
    font-weight: 600;
    text-decoration: none;
    transition: border-color 160ms ease, background-color 160ms ease, color 160ms ease, transform 160ms ease;
}

.upgrade-prompt__primary {
    border: 1px solid rgba(255, 255, 255, 0.14);
    background: rgba(255, 255, 255, 0.06);
    color: #f8fafc;
}

.upgrade-prompt__primary:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateY(-1px);
}

.upgrade-prompt__secondary {
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: rgba(255, 255, 255, 0.03);
    color: rgba(226, 232, 240, 0.84);
}

.upgrade-prompt__secondary:hover {
    border-color: rgba(148, 163, 184, 0.32);
    background: rgba(255, 255, 255, 0.06);
    color: #fff;
    transform: translateY(-1px);
}

@media (max-width: 640px) {
    .upgrade-prompt {
        padding: 0.9rem;
    }

    .upgrade-prompt__actions {
        width: 100%;
    }

    .upgrade-prompt__primary,
    .upgrade-prompt__secondary {
        width: 100%;
    }
}
</style>