<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import BrandForm from '../../Components/BrandForm.vue';

const props = defineProps({ brand: Object });
const form = useForm({
    _method: 'put',
    name: props.brand.name,
    description: props.brand.description ?? '',
    color: props.brand.color,
    logo: null,
    remove_logo: false,
});
// Files cannot be sent with a real PUT, so we POST and spoof the method.
const submit = () => form.post(`/brands/${props.brand.id}`, { forceFormData: true });
</script>

<template>
    <Head title="Edit brand" />
    <AppLayout>
        <h1 class="mb-6 text-2xl font-semibold">Edit {{ brand.name }}</h1>
        <BrandForm :form="form" :existing-logo-url="brand.logo_url" submit-label="Save changes" @submit="submit" />
    </AppLayout>
</template>
