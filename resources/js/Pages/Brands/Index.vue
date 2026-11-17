<script setup>
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import BrandLogo from '../../Components/BrandLogo.vue';

const props = defineProps({ brands: Object, filters: Object });
const isSuperadmin = usePage().props.auth.user.role === 'superadmin';
const search = ref(props.filters.search);
const find = () => router.get('/brands', { search: search.value }, { preserveState: true, replace: true });
</script>

<template>
    <Head title="Brands" />
    <AppLayout>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Brands</h1>
            <Link href="/brands/create" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">New brand</Link>
        </div>

        <form class="mb-4" @submit.prevent="find">
            <input v-model="search" placeholder="Search brand name" class="w-full max-w-sm rounded-md border border-slate-300 px-3 py-2 text-sm" />
        </form>

        <div v-if="brands.data.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div v-for="b in brands.data" :key="b.id" class="flex flex-col rounded-lg border border-[#dcdcde] bg-white p-4" :style="{ borderTop: '4px solid ' + b.color }">
                <div class="flex items-center gap-3">
                    <BrandLogo :name="b.name" :url="b.logo_url" :color="b.color" :size="48" />
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ b.name }}</div>
                        <div v-if="isSuperadmin && b.creator" class="truncate text-xs text-slate-500">by {{ b.creator }}</div>
                    </div>
                </div>
                <p class="mt-3 line-clamp-3 flex-1 text-sm text-slate-600">{{ b.description || 'No description.' }}</p>
                <div class="mt-3 text-right">
                    <Link :href="`/brands/${b.id}/edit`" class="text-sm text-indigo-600 hover:underline">Edit</Link>
                </div>
            </div>
        </div>
        <div v-else class="rounded-lg border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-slate-500">
            No brands yet.
            <Link href="/brands/create" class="text-indigo-600 hover:underline">Create your first brand</Link>.
        </div>

        <div v-if="brands.last_page > 1" class="mt-4 flex flex-wrap gap-1">
            <template v-for="l in brands.links" :key="l.label">
                <Link v-if="l.url" :href="l.url" v-html="l.label" class="rounded border px-3 py-1 text-sm" :class="l.active ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 bg-white'" />
                <span v-else v-html="l.label" class="rounded border border-slate-200 px-3 py-1 text-sm text-slate-400" />
            </template>
        </div>
    </AppLayout>
</template>
