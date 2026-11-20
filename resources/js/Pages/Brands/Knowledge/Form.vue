<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import Breadcrumb from '../../../Components/Breadcrumb.vue';
import LinksField from '../../../Components/LinksField.vue';
import TextAreaField from '../../../Components/TextAreaField.vue';
import TextField from '../../../Components/TextField.vue';

const props = defineProps({ brand: Object, item: { type: Object, default: null }, categories: Array });

const form = useForm({
    title: props.item?.title ?? '',
    category: props.item?.category ?? '',
    content: props.item?.content ?? '',
    links: (props.item?.links ?? []).map((l) => ({ url: l.url, label: l.label ?? '' })),
});

const submit = () => (props.item ? form.put(`/brands/${props.brand.id}/knowledge/${props.item.id}`) : form.post(`/brands/${props.brand.id}/knowledge`));
const destroy = () => {
    if (window.confirm(`Delete "${props.item.title}"? Content briefs that reference it will show "Removed entry".`)) router.delete(`/brands/${props.brand.id}/knowledge/${props.item.id}`);
};
</script>

<template>
    <Head :title="item ? 'Edit entry' : 'New knowledge entry'" />
    <AppLayout>
        <Breadcrumb :items="[
            { label: 'Brands', href: '/brands' },
            { label: brand.name, href: `/brands/${brand.id}?tab=knowledge` },
            { label: item ? `Edit ${item.title}` : 'New knowledge entry' },
        ]" />
        <h1 class="mb-6 text-2xl font-semibold">{{ item ? 'Edit knowledge entry' : 'New knowledge entry' }}</h1>

        <form class="max-w-3xl space-y-5 rounded-lg border border-[#dcdcde] bg-white p-5" @submit.prevent="submit">
            <TextField v-model="form.title" label="Title" star maxlength="255" :error="form.errors.title" />
            <TextField v-model="form.category" label="Category (optional)" list="knowledge-categories" placeholder="e.g. Tone of voice" maxlength="100" :error="form.errors.category" />
            <datalist id="knowledge-categories"><option v-for="c in categories" :key="c" :value="c" /></datalist>
            <TextAreaField v-model="form.content" label="Content" star :rows="14" maxlength="20000" counter placeholder="Write it so a new team member (or an AI assistant) understands the brand from this alone." :error="form.errors.content" />
            <div>
                <span class="mb-1 block text-sm font-medium text-slate-700">Links <span class="font-normal text-slate-400">(optional, e.g. brand book on Google Drive)</span></span>
                <LinksField v-model="form.links" :errors="form.errors" />
            </div>
            <div class="flex items-center gap-3">
                <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50" :disabled="form.processing">{{ item ? 'Save changes' : 'Add entry' }}</button>
                <Link :href="item ? `/brands/${brand.id}/knowledge/${item.id}` : `/brands/${brand.id}?tab=knowledge`" class="text-sm text-slate-600 hover:underline">Cancel</Link>
                <button v-if="item" type="button" class="ml-auto text-sm text-red-600 hover:underline" @click="destroy">Delete entry</button>
            </div>
        </form>
    </AppLayout>
</template>
