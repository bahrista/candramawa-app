<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import TextField from '../../Components/TextField.vue';

const props = defineProps({ roles: Array, target: Object });
const t = props.target;

const form = useForm({ name: t.name, username: t.username, email: t.email ?? '', role: t.role });
const pw = useForm({ password: '' });
const setPassword = () => pw.put(`/users/${t.id}/password`, { onSuccess: () => pw.reset(), preserveScroll: true });
</script>

<template>
    <Head title="Edit user" />
    <AppLayout>
        <h1 class="mb-6 text-2xl font-semibold">Edit {{ t.username }}</h1>
        <div class="grid gap-6 md:grid-cols-2">
            <form class="space-y-4 rounded-lg border border-slate-200 bg-white p-5" @submit.prevent="form.put(`/users/${t.id}`)">
                <TextField v-model="form.name" label="Name" required :error="form.errors.name" />
                <TextField v-model="form.username" label="Username" required :error="form.errors.username" />
                <TextField v-model="form.email" label="Email (optional)" type="email" :error="form.errors.email" />
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-slate-700">Role</span>
                    <select v-model="form.role" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm capitalize">
                        <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                    </select>
                    <span v-if="form.errors.role" class="text-xs text-red-600">{{ form.errors.role }}</span>
                </label>
                <div class="flex gap-3">
                    <button class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700" :disabled="form.processing">Save</button>
                    <Link href="/users" class="px-3 py-2 text-sm text-slate-600">Back</Link>
                </div>
            </form>
            <form class="space-y-4 rounded-lg border border-slate-200 bg-white p-5" @submit.prevent="setPassword">
                <h2 class="font-medium">Set temporary password</h2>
                <p class="text-sm text-slate-500">Ends all sessions of this user. They must change the password at next login.</p>
                <TextField v-model="pw.password" label="New temporary password" type="text" required autocomplete="off" :error="pw.errors.password" />
                <button class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-900" :disabled="pw.processing">Set password</button>
            </form>
        </div>
    </AppLayout>
</template>
