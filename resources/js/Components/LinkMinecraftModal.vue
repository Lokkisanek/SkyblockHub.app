<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Modal from '@/Components/Modal.vue';

const { t } = useI18n();

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const page = usePage();
const discordUsername = page.props.auth?.user?.discord_username ?? '';

const currentStep = ref(0);

const steps = computed(() => [
    {
        title: t('mcModal.step1Title'),
        instruction: t('mcModal.step1Desc'),
        icon: '🎮',
    },
    {
        title: t('mcModal.step2Title'),
        instruction: t('mcModal.step2Desc'),
        icon: '👤',
    },
    {
        title: t('mcModal.step3Title'),
        instruction: t('mcModal.step3Desc'),
        icon: '🐦',
    },
    {
        title: t('mcModal.step4Title'),
        instruction: t('mcModal.step4Desc', { username: discordUsername }),
        icon: '💬',
    },
    {
        title: t('mcModal.step5Title'),
        instruction: t('mcModal.step5Desc'),
        icon: '✅',
    },
]);

const form = useForm({
    minecraft_username: '',
});

function next() {
    if (currentStep.value < steps.length - 1) {
        currentStep.value++;
    }
}

function prev() {
    if (currentStep.value > 0) {
        currentStep.value--;
    }
}

function verify() {
    form.post(route('mc.link.verify'), {
        preserveScroll: true,
        onSuccess: () => {
            close();
        },
    });
}

function close() {
    currentStep.value = 0;
    form.clearErrors();
    form.reset();
    emit('close');
}
</script>

<template>
    <Modal :show="show" @close="close" max-width="lg">
        <div class="p-6">
            <h2 class="text-lg font-medium text-white">
                {{ $t('mcModal.title') }}
            </h2>

            <p class="mt-1 text-sm text-neutral">
                {{ $t('mcModal.description') }}
            </p>

            <!-- Step indicator -->
            <div class="mt-4 flex items-center gap-1">
                <template v-for="(step, i) in steps" :key="i">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition-colors"
                        :class="i <= currentStep
                            ? 'bg-[#0bca51] text-surface-900'
                            : 'bg-surface-700 text-neutral'"
                    >
                        {{ i + 1 }}
                    </div>
                    <div
                        v-if="i < steps.length - 1"
                        class="h-0.5 flex-1 transition-colors"
                        :class="i < currentStep ? 'bg-[#0bca51]' : 'bg-surface-700'"
                    />
                </template>
            </div>

            <!-- Current step -->
            <div class="mt-6 rounded-lg border border-border bg-surface-700 p-4">
                <div class="flex items-start gap-3">
                    <span class="text-2xl">{{ steps[currentStep].icon }}</span>
                    <div>
                        <h3 class="font-semibold text-white">
                            {{ steps[currentStep].title }}
                        </h3>
                        <p class="mt-1 text-sm text-neutral">
                            {{ steps[currentStep].instruction }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- MC Username input (only on last step) -->
            <div v-if="currentStep === steps.length - 1" class="mt-4">
                <label for="mc-username" class="block text-sm font-medium text-neutral">
                    {{ $t('mcModal.mcPlaceholder') }}
                </label>
                <input
                    id="mc-username"
                    v-model="form.minecraft_username"
                    type="text"
                    :placeholder="$t('mcModal.mcPlaceholder')"
                    class="mt-1 block w-full rounded-md border-border bg-surface-700 text-white shadow-sm placeholder-neutral/50 focus:border-[#0bca51] focus:ring-[#0bca51] sm:text-sm"
                    @keyup.enter="verify"
                />
                <p v-if="form.errors.minecraft_username" class="mt-1.5 text-xs text-red-400">
                    {{ form.errors.minecraft_username }}
                </p>
            </div>

            <!-- Navigation -->
            <div class="mt-6 flex items-center justify-between">
                <button
                    v-if="currentStep > 0"
                    @click="prev"
                    class="rounded-md border border-border bg-surface-700 px-4 py-2 text-sm font-medium text-neutral hover:bg-surface-600 transition-colors"
                >
                    {{ $t('mcModal.back') }}
                </button>
                <div v-else />

                <div class="flex gap-2">
                    <button
                        @click="close"
                        class="rounded-md border border-border bg-surface-700 px-4 py-2 text-sm font-medium text-neutral hover:bg-surface-600 transition-colors"
                    >
                        {{ $t('mcModal.cancel') }}
                    </button>

                    <button
                        v-if="currentStep < steps.length - 1"
                        @click="next"
                        class="rounded-md bg-[#0bca51] px-4 py-2 text-sm font-medium text-surface-900 hover:bg-[#0ab847] transition-colors"
                    >
                        {{ $t('mcModal.next') }}
                    </button>

                    <button
                        v-else
                        @click="verify"
                        :disabled="form.processing || !form.minecraft_username.trim()"
                        class="rounded-md bg-[#0bca51] px-4 py-2 text-sm font-medium text-surface-900 hover:bg-[#0ab847] disabled:opacity-50 transition-colors"
                    >
                        <span v-if="form.processing">{{ $t('mcModal.verifying') }}</span>
                        <span v-else>{{ $t('mcModal.verify') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>
