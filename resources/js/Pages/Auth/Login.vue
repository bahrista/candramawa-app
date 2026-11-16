<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import TextField from '../../Components/TextField.vue';

const form = useForm({ username: '', password: '', remember: false });
const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Log in" />
    <div class="flex min-h-screen items-center justify-center px-4">
        <form class="w-full max-w-sm space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <h1 class="text-xl font-semibold text-indigo-700">Candramawa App</h1>
            <p class="text-sm text-slate-500">Log in with your username and password.</p>
            <TextField v-model="form.username" label="Username" autocomplete="username" required :error="form.errors.username" />
            <TextField v-model="form.password" label="Password" type="password" autocomplete="current-password" required :error="form.errors.password" />
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input v-model="form.remember" type="checkbox" class="rounded border-slate-300" /> Remember me
            </label>
            <button class="w-full rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50" :disabled="form.processing">
                Log in
            </button>
        </form>
    </div>
</template>
