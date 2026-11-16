<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import TextField from '../../Components/TextField.vue';

defineProps({ roles: Array });
const form = useForm({ name: '', username: '', email: '', role: 'user', password: '' });
</script>

<template>
    <Head title="New user" />
    <AppLayout>
        <h1 class="mb-6 text-2xl font-semibold">New user</h1>
        <form class="max-w-lg space-y-4 rounded-lg border border-slate-200 bg-white p-5" @submit.prevent="form.post('/users')">
            <TextField v-model="form.name" label="Name" required :error="form.errors.name" />
            <TextField v-model="form.username" label="Username" required hint="Lowercase letters, numbers, dots and underscores. 3 to 50 characters." :error="form.errors.username" />
            <TextField v-model="form.email" label="Email (optional)" type="email" :error="form.errors.email" />
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-slate-700">Role</span>
                <select v-model="form.role" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm capitalize">
                    <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                </select>
                <span v-if="form.errors.role" class="text-xs text-red-600">{{ form.errors.role }}</span>
            </label>
            <TextField v-model="form.password" label="Temporary password" type="text" required autocomplete="off" hint="At least 8 characters. The user must change it at first login." :error="form.errors.password" />
            <div class="flex gap-3">
                <button class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700" :disabled="form.processing">Create user</button>
                <Link href="/users" class="px-3 py-2 text-sm text-slate-600">Cancel</Link>
            </div>
        </form>
    </AppLayout>
</template>
