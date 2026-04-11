<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const confirmingUserDeletion = ref(false);

const form = useForm({});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-base font-semibold text-white">
                {{ $t('profile.delete.heading') }}
            </h2>

            <p class="mt-1 text-sm text-neutral">
                {{ $t('profile.delete.description') }}
            </p>
        </header>

        <DangerButton @click="confirmUserDeletion">{{ $t('profile.delete.button') }}</DangerButton>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-white">
                    {{ $t('profile.delete.confirmTitle') }}
                </h2>

                <p class="mt-1 text-sm text-neutral">
                    {{ $t('profile.delete.confirmDescription') }}
                    {{ $t('profile.delete.confirmIrreversible') }}
                </p>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal">
                        {{ $t('profile.delete.cancel') }}
                    </SecondaryButton>

                    <DangerButton
                        class="ms-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        {{ $t('profile.delete.button') }}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
