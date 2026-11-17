<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import BrandForm from '../../Components/BrandForm.vue';

const props = defineProps({ defaultColor: String });
const form = useForm({ name: '', description: '', color: props.defaultColor, logo: null, remove_logo: false });
const submit = () => form.post('/brands', { forceFormData: true });
</script>

<template>
    <Head title="New brand" />
    <AppLayout>
        <h1 class="mb-6 text-2xl font-semibold">New brand</h1>
        <BrandForm :form="form" submit-label="Create brand" @submit="submit" />
    </AppLayout>
</template>
