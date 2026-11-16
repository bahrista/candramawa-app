<script setup>
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ users: Object, filters: Object });
const me = usePage().props.auth.user;
const errors = usePage().props.errors;
const search = ref(props.filters.search);

const find = () => router.get('/users', { search: search.value }, { preserveState: true, replace: true });
const toggle = (u) => {
    const verb = u.is_active ? 'Deactivate' : 'Activate';
    if (confirm(`${verb} ${u.username}?`)) router.patch(`/users/${u.id}/active`, {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Users" />
    <AppLayout>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Users</h1>
            <Link href="/users/create" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">New user</Link>
        </div>
        <div v-if="errors.user" class="mb-4 rounded-md bg-red-50 px-4 py-2 text-sm text-red-700">{{ errors.user }}</div>
        <form class="mb-4" @submit.prevent="find">
            <input v-model="search" placeholder="Search name, username or email" class="w-full max-w-sm rounded-md border border-slate-300 px-3 py-2 text-sm" />
        </form>
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr><th class="px-4 py-2">Name</th><th class="px-4 py-2">Username</th><th class="px-4 py-2">Role</th><th class="px-4 py-2">Status</th><th class="px-4 py-2"></th></tr>
                </thead>
                <tbody>
                    <tr v-for="u in users.data" :key="u.id" class="border-t border-slate-100">
                        <td class="px-4 py-2">{{ u.name }}<div class="text-xs text-slate-500">{{ u.email }}</div></td>
                        <td class="px-4 py-2 font-mono">{{ u.username }}</td>
                        <td class="px-4 py-2 capitalize">{{ u.role }}</td>
                        <td class="px-4 py-2">
                            <span :class="u.is_active ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-600'" class="rounded-full px-2 py-0.5 text-xs">
                                {{ u.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="space-x-3 px-4 py-2 text-right">
                            <Link :href="`/users/${u.id}/edit`" class="text-indigo-600 hover:underline">Edit</Link>
                            <button v-if="u.id !== me.id" class="text-slate-600 hover:underline" @click="toggle(u)">
                                {{ u.is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!users.data.length"><td colspan="5" class="px-4 py-6 text-center text-slate-500">No users found.</td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="users.last_page > 1" class="mt-4 flex flex-wrap gap-1">
            <template v-for="l in users.links" :key="l.label">
                <Link v-if="l.url" :href="l.url" v-html="l.label" class="rounded border px-3 py-1 text-sm" :class="l.active ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 bg-white'" />
                <span v-else v-html="l.label" class="rounded border border-slate-200 px-3 py-1 text-sm text-slate-400" />
            </template>
        </div>
    </AppLayout>
</template>
