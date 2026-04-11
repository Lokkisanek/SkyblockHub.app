<script setup>
import { ref, onMounted } from 'vue';

const visible = ref(false);
const STORAGE_KEY = 'survey_dismissed';
const SURVEY_URL = 'https://docs.google.com/forms/d/e/1FAIpQLSdNdtZVw5XQmDyw825ZimTJvhg8a-2Dxl03FQ018owibC81sg/viewform?usp=sf_link';

onMounted(() => {
    if (!localStorage.getItem(STORAGE_KEY)) {
        setTimeout(() => {
            visible.value = true;
        }, 3000);
    }
});

function takeSurvey() {
    localStorage.setItem(STORAGE_KEY, Date.now().toString());
    visible.value = false;
    window.open(SURVEY_URL, '_blank', 'noopener,noreferrer');
}

function dismiss() {
    localStorage.setItem(STORAGE_KEY, Date.now().toString());
    visible.value = false;
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
            class="fixed bottom-5 left-5 z-50 w-full max-w-sm rounded-2xl border border-white/10 bg-surface-900/95 p-5 shadow-[0_16px_48px_rgba(0,0,0,0.5)] backdrop-blur-xl"
        >
            <h3 class="text-base font-bold text-white">{{ $t('survey.title') }}</h3>
            <p class="mt-2 text-sm leading-relaxed text-white/55">
                {{ $t('survey.description') }}
            </p>
            <p class="mt-2 text-sm leading-relaxed text-white/45">
                {{ $t('survey.helpful') }}
            </p>
            <div class="mt-4 flex items-center gap-3">
                <button
                    @click="takeSurvey"
                    class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-500"
                >
                    {{ $t('survey.take') }}
                </button>
                <button
                    @click="dismiss"
                    class="text-sm font-medium text-white/50 transition hover:text-white/80"
                >
                    {{ $t('survey.dismiss') }}
                </button>
            </div>
        </div>
    </Transition>
</template>
