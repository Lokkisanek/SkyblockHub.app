<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <div class="py-4">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <p class="text-xs text-neutral">
                    You're logged in. Use the navigation above to access the Bazaar or look up profile stats.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
