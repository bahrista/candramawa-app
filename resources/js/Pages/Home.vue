<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';

const user = usePage().props.auth.user;
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout>
        <h1 class="text-2xl font-semibold">Welcome, {{ user.name }}</h1>
        <p class="mt-2 text-slate-600">Content planning features are coming soon.</p>
    </AppLayout>
</template>
