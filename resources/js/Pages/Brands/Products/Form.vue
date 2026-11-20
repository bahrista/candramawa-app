<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import Breadcrumb from '../../../Components/Breadcrumb.vue';
import LinksField from '../../../Components/LinksField.vue';
import SelectField from '../../../Components/SelectField.vue';
import TextAreaField from '../../../Components/TextAreaField.vue';
import TextField from '../../../Components/TextField.vue';

const props = defineProps({ brand: Object, item: { type: Object, default: null } });

const form = useForm({
    type: props.item?.type ?? 'product',
    name: props.item?.name ?? '',
    description: props.item?.description ?? '',
    key_benefits: props.item?.key_benefits ?? '',
    price: props.item?.price ?? '',
    is_active: props.item?.is_active ?? true,
    links: (props.item?.links ?? []).map((l) => ({ url: l.url, label: l.label ?? '' })),
});

const submit = () => (props.item ? form.put(`/brands/${props.brand.id}/products/${props.item.id}`) : form.post(`/brands/${props.brand.id}/products`));
const destroy = () => {
    if (window.confirm(`Delete "${props.item.name}"? Content briefs that reference it will show "Removed entry".`)) router.delete(`/brands/${props.brand.id}/products/${props.item.id}`);
};
</script>

<template>
    <Head :title="item ? 'Edit product or service' : 'New product or service'" />
    <AppLayout>
        <Breadcrumb :items="[
            { label: 'Brands', href: '/brands' },
            { label: brand.name, href: `/brands/${brand.id}?tab=products` },
            { label: item ? `Edit ${item.name}` : 'New product or service' },
        ]" />
        <h1 class="mb-6 text-2xl font-semibold">{{ item ? 'Edit product or service' : 'New product or service' }}</h1>

        <form class="max-w-3xl space-y-5 rounded-lg border border-[#dcdcde] bg-white p-5" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-[12rem_minmax(0,1fr)]">
                <SelectField v-model="form.type" label="Type" star :options="[{ value: 'product', label: 'Product' }, { value: 'service', label: 'Service' }]" :error="form.errors.type" />
                <TextField v-model="form.name" label="Name" star maxlength="255" :error="form.errors.name" />
            </div>
            <TextAreaField v-model="form.description" label="Description" :rows="5" maxlength="5000" counter placeholder="What is it, and who is it for?" :error="form.errors.description" />
            <TextAreaField v-model="form.key_benefits" label="Key benefits (optional)" :rows="4" maxlength="3000" placeholder="One benefit per line." :error="form.errors.key_benefits" />
            <TextField v-model="form.price" label="Price (optional)" maxlength="100" placeholder="e.g. Rp 32.000, or from Rp 500.000 / month" :error="form.errors.price" />
            <div>
                <span class="mb-1 block text-sm font-medium text-slate-700">Links <span class="font-normal text-slate-400">(optional, e.g. photos or the online shop)</span></span>
                <LinksField v-model="form.links" :errors="form.errors" />
            </div>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" /> Active (offered in the brief’s reference list)</label>
            <div class="flex items-center gap-3">
                <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50" :disabled="form.processing">{{ item ? 'Save changes' : 'Add entry' }}</button>
                <Link :href="item ? `/brands/${brand.id}/products/${item.id}` : `/brands/${brand.id}?tab=products`" class="text-sm text-slate-600 hover:underline">Cancel</Link>
                <button v-if="item" type="button" class="ml-auto text-sm text-red-600 hover:underline" @click="destroy">Delete entry</button>
            </div>
        </form>
    </AppLayout>
</template>
