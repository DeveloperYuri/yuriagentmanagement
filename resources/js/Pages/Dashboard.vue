<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <template #header>Dashboard</template>
        <div class="p-6 bg-white rounded-lg shadow">
            Selamat Datang di Yuri Agent Management!
        </div>
    </AuthenticatedLayout>
</template>