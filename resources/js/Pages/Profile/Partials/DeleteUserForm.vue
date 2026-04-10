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
                Smazat účet
            </h2>

            <p class="mt-1 text-sm text-neutral">
                Po smazání účtu budou všechna data trvale odstraněna.
                Před smazáním si stáhni všechna data, která chceš zachovat.
            </p>
        </header>

        <DangerButton @click="confirmUserDeletion">Smazat účet</DangerButton>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-white">
                    Opravdu chceš smazat svůj účet?
                </h2>

                <p class="mt-1 text-sm text-neutral">
                    Po smazání účtu budou všechna data trvale odstraněna.
                    Tato akce je nevratná.
                    Pokud mas aktivni subscription plan, bude automaticky zrusen.
                </p>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal">
                        Zrušit
                    </SecondaryButton>

                    <DangerButton
                        class="ms-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        Smazat účet
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
